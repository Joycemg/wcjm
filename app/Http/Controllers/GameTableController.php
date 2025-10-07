<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\User; // ← Importa tu User para tipar correctamente
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class GameTableController extends Controller
{
    /** Disk para imágenes (soporta public/S3/Backblaze en Hostinger) */
    private function imageDisk(): string
    {
        return (string) config('mesas.image_disk', config('filesystems.default', 'public'));
    }

    /* ========================= Listado ========================= */
    public function index(Request $request): ViewContract
    {
        $now = $this->nowTz();
        $q = trim((string) $request->query('q', ''));
        $like = '%' . addcslashes($q, "%_\\") . '%';

        $status = $request->query('status');
        $status = \in_array($status, ['open', 'closed'], true) ? $status : null;

        $query = GameTable::query()
            ->select(['id', 'title', 'description', 'capacity', 'image_path', 'image_url', 'is_open', 'opens_at', 'created_at', 'updated_at'])
            ->when(method_exists(GameTable::class, 'scopeSelectIsOpenNow'), fn($qb) => $qb->selectIsOpenNow())
            ->withCount(['signups as signups_count' => fn($q2) => $q2->where('is_counted', 1)])
            ->when(
                method_exists(GameTable::class, 'recentSignups'),
                fn($qb) => $qb->with([
                    'recentSignups' => fn($q3) => $q3->where('is_counted', 1)->with([
                        'user:id,username,name,email,avatar_path,updated_at',
                    ]),
                ])
            )
            ->orderByDesc('created_at');

        if ($q !== '') {
            $query->where(function ($w) use ($like) {
                $w->where('title', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like);
            });
        }

        if ($status === 'open') {
            $query->where('is_open', true)
                ->where(fn($w) => $w->whereNull('opens_at')->orWhere('opens_at', '<=', $now));
        } elseif ($status === 'closed') {
            $query->where(fn($w) => $w
                ->where('is_open', false)
                ->orWhere(fn($w2) => $w2->where('is_open', true)->where('opens_at', '>', $now)));
        }

        $auth = $this->optionalUser($request); // ← tipado ?User
        $myMesaId = null;

        if ($auth) {
            $uid = (int) $auth->id;
            $query->withExists(['signups as already_signed' => fn($q2) => $q2->where('user_id', $uid)]);
            $myMesaId = Signup::where('user_id', $uid)->value('game_table_id');
        }

        $perPage = $this->perPage($request, 12, 48);
        $tables = $query->paginate($perPage)->withQueryString();

        return view($this->pickView(['mesas.index', 'tables.index']), compact('tables', 'myMesaId'));
    }

    /* ========================= Detalle ========================= */
    public function show(GameTable $mesa): ViewContract
    {
        $mesa->loadCount(['signups as signups_count' => fn($q) => $q->where('is_counted', 1)]);
        $mesa->loadMissing([
            'manager:id,name,username,email,avatar_path,updated_at',
            'creator:id,name,username,email,avatar_path,updated_at',
        ]);
        $mesa->setAttribute('is_open_now', $this->computeIsOpenNow($mesa));

        $capacity = max(0, (int) $mesa->capacity);
        $waitlistMax = (int) config('mesas.waitlist_max', 10000);
        $need = $capacity + $waitlistMax;

        $signups = $mesa->signups()
            ->where('is_counted', 1)
            ->with(['user:id,username,name,email,avatar_path,updated_at'])
            ->reorder('created_at')->orderBy('id')
            ->limit($need)
            ->get();

        $players = $signups->take($capacity)->values();
        $waitlist = $signups->slice($capacity)->values()->take($waitlistMax);

        $auth = $this->optionalUser(request()); // ← ?User
        $context = $this->mesaUserContext($auth, $mesa);
        $alreadySigned = $context['isSigned'];
        $myMesaId = $auth ? Signup::where('user_id', $auth->id)->value('game_table_id') : null;

        $isOwner = $context['isOwner'];
        $isManager = $context['isManager'];
        $isAdmin = $context['isAdmin'];
        $canManageHonor = $isOwner || $isManager || $isAdmin;
        $canViewNotes = $context['isSigned'] || $isOwner || $isManager || $isAdmin;
        $managerCountsAsPlayer = (bool) $mesa->manager_counts_as_player;

        return view(
            $this->pickView(['mesas.show', 'tables.show']),
            compact(
                'mesa',
                'players',
                'waitlist',
                'alreadySigned',
                'myMesaId',
                'isOwner',
                'isManager',
                'isAdmin',
                'canManageHonor',
                'canViewNotes',
                'managerCountsAsPlayer'
            )
        );
    }

    public function notes(Request $request, GameTable $mesa): ViewContract
    {
        $auth = $this->requireUser($request);
        $context = $this->mesaUserContext($auth, $mesa);

        abort_unless($context['isSigned'] || $context['isOwner'] || $context['isManager'] || $context['isAdmin'], 403);

        $mesa->loadMissing([
            'manager:id,name,username,email,avatar_path,updated_at',
            'creator:id,name,username,email,avatar_path,updated_at',
        ]);

        $players = $mesa->signups()
            ->with(['user:id,username,name,email,avatar_path,updated_at'])
            ->orderByDesc('is_manager')
            ->orderBy('created_at')
            ->get();

        $note = old('manager_note', (string) ($mesa->manager_note ?? ''));
        $canEdit = $context['isManager'] || $context['isOwner'] || $context['isAdmin'];

        return view(
            $this->pickView(['mesas.notes', 'tables.notes']),
            compact('mesa', 'players', 'note', 'canEdit') + $context
        );
    }

    public function updateNotes(Request $request, GameTable $mesa): RedirectResponse
    {
        $auth = $this->requireUser($request);
        $context = $this->mesaUserContext($auth, $mesa);
        abort_unless($context['isManager'] || $context['isOwner'] || $context['isAdmin'], 403);

        $data = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $note = isset($data['manager_note']) ? Str::of($data['manager_note'])->trim()->toString() : null;
        $mesa->manager_note = $note === '' ? null : $note;
        $mesa->save();

        return redirect()->route('mesas.notes', $mesa)->with('ok', 'Nota de la mesa actualizada.');
    }

    public function updateManagerPlaying(Request $request, GameTable $mesa): RedirectResponse
    {
        $auth = $this->requireUser($request);
        $context = $this->mesaUserContext($auth, $mesa);
        abort_unless($context['isManager'] || $context['isAdmin'], 403);

        $data = $request->validate([
            'playing' => ['required', 'boolean'],
        ]);

        $mesa->manager_counts_as_player = (bool) $data['playing'];
        $mesa->save();

        $message = $mesa->manager_counts_as_player
            ? 'Volviste a figurar como jugador.'
            : 'Quedaste como encargado sin sumar un lugar de jugador.';

        return back()->with('ok', $message);
    }

    /* ========================= Crear / Guardar ========================= */
    public function create(Request $request): ViewContract
    {
        $this->requireUser($request); // asegura sesión (User) y quita warning
        $managerCandidates = User::query()
            ->select(['id', 'name', 'username', 'email'])
            ->orderByRaw("COALESCE(NULLIF(name, ''), username, email) ASC")
            ->limit(50)
            ->get();

        return view(
            $this->pickView(['mesas.create', 'tables.create']),
            compact('managerCandidates')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $auth = $this->requireUser($request); // ← User tipado

        $data = $this->validateTable($request);
        $opensAt = $this->normalizeOpensAt($data['opens_at'] ?? null);

        // archivo > URL
        $path = $this->storeImageIfAny($request);
        $imgUrl = $path ? null : ($data['image_url'] ?? null);

        $isOpen = (bool) ($data['is_open'] ?? false);
        if ($opensAt && $opensAt->isFuture())
            $isOpen = true;

        $mesa = GameTable::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'capacity' => $data['capacity'],
            'image_path' => $path,
            'image_url' => $imgUrl,
            'is_open' => $isOpen,
            'opens_at' => $opensAt,
            'join_url' => $data['join_url'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'manager_counts_as_player' => (bool) ($data['manager_counts_as_player'] ?? true),
            'manager_note' => $data['manager_note'] ?? null,
            'created_by' => $auth->id,
        ]);

        $mesa->touch();
        return redirect()->route('mesas.show', $mesa)->with('ok', 'Mesa creada');
    }

    /* ========================= Editar / Actualizar ========================= */
    public function edit(Request $request, GameTable $mesa): ViewContract
    {
        $this->authorizeMesa($request, $mesa);

        $tz = $this->tz();
        $opensAtObj = $mesa->opens_at ? $this->toTz($mesa->opens_at, $tz) : Carbon::now($tz)->setTime(10, 15, 0);
        $opensAtValue = $opensAtObj->format('Y-m-d\TH:i');

        $managerCandidates = User::query()
            ->select(['id', 'name', 'username', 'email'])
            ->orderByRaw("COALESCE(NULLIF(name, ''), username, email) ASC")
            ->limit(50)
            ->get();

        if ($mesa->manager_id && !$managerCandidates->contains('id', $mesa->manager_id)) {
            $currentManager = User::query()
                ->select(['id', 'name', 'username', 'email'])
                ->find($mesa->manager_id);
            if ($currentManager) {
                $managerCandidates->push($currentManager);
            }
        }

        return view(
            $this->pickView(['mesas.edit', 'tables.edit']),
            compact('mesa', 'tz', 'opensAtValue', 'managerCandidates')
        );
    }

    public function update(Request $request, GameTable $mesa): RedirectResponse
    {
        $this->authorizeMesa($request, $mesa);

        $data = $this->validateTable($request);

        // archivo > remove > url
        if ($request->hasFile('image')) {
            if ($mesa->image_path)
                $this->tryDeleteLocal($mesa->image_path);
            $mesa->image_path = $this->storeImageIfAny($request);
            $mesa->image_url = null;
        } elseif ($request->boolean('remove_image')) {
            if ($mesa->image_path)
                $this->tryDeleteLocal($mesa->image_path);
            $mesa->image_path = null;
        } elseif (!empty($data['image_url'])) {
            if ($mesa->image_path)
                $this->tryDeleteLocal($mesa->image_path);
            $mesa->image_path = null;
            $mesa->image_url = $data['image_url'];
        }

        $opensAt = $this->normalizeOpensAt($data['opens_at'] ?? null);
        $isOpen = (bool) ($data['is_open'] ?? $mesa->is_open);
        if ($opensAt && $opensAt->isFuture())
            $isOpen = true;

        $auth = $this->requireUser($request); // ← User tipado
        $isOwner = (int) $auth->id === (int) $mesa->created_by;
        $isAdmin = ($auth->role ?? null) === 'admin';

        $mesa->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'capacity' => $data['capacity'],
            'is_open' => $isOpen,
            'opens_at' => $opensAt,
            'join_url' => $data['join_url'] ?? $mesa->join_url,
            'manager_counts_as_player' => (bool) ($data['manager_counts_as_player'] ?? $mesa->manager_counts_as_player),
            'manager_note' => $data['manager_note'] ?? $mesa->manager_note,
        ]);

        if ($isOwner || $isAdmin) {
            $mesa->manager_id = $data['manager_id'] ?? null;
        }

        $mesa->save();
        $mesa->touch();

        return redirect()->route('mesas.show', $mesa)->with('ok', 'Mesa actualizada');
    }

    /* ========================= Eliminar ========================= */
    public function destroy(Request $request, GameTable $mesa): RedirectResponse
    {
        $this->authorizeMesa($request, $mesa, /*allowManager*/ true);

        if ($mesa->image_path)
            $this->tryDeleteLocal($mesa->image_path);
        $mesa->delete();

        return redirect()->route('home')->with('ok', 'Mesa eliminada');
    }

    /* ========================= Abrir / Cerrar ========================= */
    public function open(Request $request, GameTable $mesa): RedirectResponse
    {
        $this->authorizeMesa($request, $mesa, /*allowManager*/ true);

        $now = $this->nowTz()->startOfMinute();
        $current = $mesa->opens_at ? $this->toTz($mesa->opens_at, $this->tz()) : null;

        $mesa->forceFill([
            'is_open' => true,
            'opens_at' => (!$current || $current->isFuture()) ? $now : $mesa->opens_at,
        ])->save();

        $mesa->touch();
        return back()->with('ok', 'Mesa abierta');
    }

    public function close(Request $request, GameTable $mesa): RedirectResponse
    {
        $this->authorizeMesa($request, $mesa, /*allowManager*/ true);

        $mesa->forceFill(['is_open' => false])->save();
        $mesa->touch();

        return back()->with('ok', 'Mesa cerrada');
    }

    /* ========================= Atajos ========================= */
    public function mine(Request $request): RedirectResponse
    {
        $auth = $this->requireUser($request); // ← User tipado

        $mesaId = Signup::where('user_id', $auth->id)->value('game_table_id');
        if (!$mesaId)
            return redirect()->route('mesas.index')->with('err', 'No estás anotado en ninguna mesa.');

        return redirect()->route('mesas.show', $mesaId);
    }

    /* ========================= Helpers ========================= */
    private function authorizeMesa(Request $request, GameTable $mesa, bool $allowManager = false): void
    {
        $auth = $this->requireUser($request); // ← User tipado

        $isOwner = (int) ($mesa->created_by ?? 0) === (int) $auth->id;
        $isManager = $allowManager && ((int) ($mesa->manager_id ?? 0) === (int) $auth->id);
        $isAdmin = (($auth->role ?? null) === 'admin');

        abort_unless($isOwner || $isManager || $isAdmin, 403);
    }

    /** Devuelve el usuario autenticado tipado o aborta 403 (quita warnings) */
    private function requireUser(Request $request): User
    {
        $u = $request->user();
        abort_unless($u instanceof User, 403);
        return $u;
    }

    /** Devuelve el usuario autenticado tipado o null (para vistas públicas) */
    private function optionalUser(Request $request): ?User
    {
        $u = $request->user();
        return $u instanceof User ? $u : null;
    }

    private function validateTable(Request $request): array
    {
        return $this->validateInput($request, [
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'capacity' => 'required|integer|min:1|max:1000',
            'opens_at' => 'nullable|date',
            'is_open' => 'nullable|boolean',
            'image' => 'nullable|image|max:' . (int) config('mesas.image_max_kb', 2048),
            'image_url' => 'nullable|string|max:2048|url|starts_with:https://,http://',
            'remove_image' => 'nullable|boolean',
            'join_url' => 'nullable|string|max:2048|url|starts_with:https://,http://',
            'manager_id' => 'nullable|integer|exists:users,id',
            'manager_counts_as_player' => 'nullable|boolean',
            'manager_note' => 'nullable|string|max:2000',
        ]);
    }

    private function normalizeOpensAt(?string $value): ?Carbon
    {
        if (empty($value))
            return null;
        $tz = $this->tz();
        $c = Carbon::parse($value, $tz);
        return $c->setTime((int) $c->format('H'), (int) $c->format('i'), 0);
    }

    /**
     * Información de contexto para el usuario autenticado respecto a una mesa.
     *
     * @return array{isOwner:bool,isManager:bool,isAdmin:bool,isSigned:bool}
     */
    private function mesaUserContext(?User $auth, GameTable $mesa): array
    {
        $isOwner = $auth ? ((int) $mesa->created_by === (int) $auth->id) : false;
        $isManager = $auth ? ((int) $mesa->manager_id === (int) $auth->id) : false;
        $isAdmin = $auth ? (($auth->role ?? null) === 'admin') : false;

        $isSigned = false;
        if ($auth) {
            $isSigned = Signup::query()
                ->where('game_table_id', (int) $mesa->id)
                ->where('user_id', (int) $auth->id)
                ->exists();
        }

        return compact('isOwner', 'isManager', 'isAdmin', 'isSigned');
    }

    private function storeImageIfAny(Request $request): ?string
    {
        return $request->hasFile('image')
            ? $request->file('image')->store('mesas', $this->imageDisk())
            : null;
    }

    private function tryDeleteLocal(string $path): void
    {
        try {
            Storage::disk($this->imageDisk())->delete($path);
        } catch (\Throwable) {
            // hosting compartido: no romper por errores de FS
        }
    }

    private function computeIsOpenNow(GameTable $mesa): bool
    {
        if (!$mesa->is_open)
            return false;

        $opensRaw = $mesa->getAttribute('opens_at');
        if ($opensRaw === null)
            return true;

        $openAt = $opensRaw instanceof CarbonInterface
            ? $opensRaw->copy()->timezone($this->tz())
            : Carbon::parse((string) $opensRaw, $this->tz());

        return $this->nowTz()->greaterThanOrEqualTo($openAt);
    }

    private function pickView(array $candidates, ?string $fallback = null): string
    {
        foreach ($candidates as $v)
            if (View::exists($v))
                return $v;
        if ($fallback && View::exists($fallback))
            return $fallback;
        abort(404, 'Ninguna vista encontrada para: ' . implode(', ', $candidates));
    }

    private function toTz(null|string|CarbonInterface $dt, string $tz): Carbon
    {
        if ($dt instanceof CarbonInterface)
            return Carbon::instance($dt)->timezone($tz);
        return Carbon::parse((string) $dt, $tz);
    }
}
