<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\HonorEvent;
use App\Models\User;
use App\Support\DatabaseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;

class AttendanceController extends Controller
{
    /**
     * Actualiza asistencia/comportamiento de un signup y aplica honor correspondiente.
     * Rol permitido: creador/manager de la mesa o admin.
     */
    public function update(Request $request, GameTable $mesa, Signup $signup): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Normalizamos selects con valor "sin cambios" antes de validar.
        if ($request->input('attended') === '_keep_') {
            $request->request->remove('attended');
        }
        if ($request->input('behavior') === '_keep_') {
            $request->request->remove('behavior');
        }

        $isOwner = (int) ($mesa->created_by ?? 0) === (int) $user->id;
        $isManager = (int) ($mesa->manager_id ?? 0) === (int) $user->id;
        $isAdmin = (string) ($user->role ?? '') === 'admin';
        abort_unless($isOwner || $isManager || $isAdmin, 403);

        // Aseguramos que el signup pertenezca a la mesa indicada
        abort_unless((int) $signup->game_table_id === (int) $mesa->id, 404);

        // Validación nativa (sin helpers externos)
        $data = $request->validate([
            'attended' => ['nullable', 'boolean'],
            'no_show' => ['nullable', 'boolean'],
            'behavior' => ['nullable', 'in:good,regular,bad'],
        ]);

        DB::transaction(function () use ($data, $mesa, $signup, $user) {
            // Bloqueo pesimista para consistencia en hosting compartido
            /** @var Signup $row */
            $row = DatabaseUtils::applyPessimisticLock(
                Signup::query()
                    ->select(['id', 'user_id', 'game_table_id', 'attended', 'behavior'])
                    ->whereKey($signup->id)
            )->firstOrFail();

            $prevAttended = (bool) ($row->attended ?? false);
            $prevBehavior = (string) ($row->behavior ?? 'regular');

            // Persistimos cambios de estado en Signup (si vinieron en la request)
            $dirty = false;
            if (array_key_exists('attended', $data)) {
                $row->attended = (bool) $data['attended'];
                $dirty = true;
            }
            if (array_key_exists('behavior', $data)) {
                $row->behavior = (string) $data['behavior'];
                $dirty = true;
            }
            if ($dirty) {
                $row->save();
            }

            $mesaId = (int) $mesa->id;
            $signupId = (int) $row->id;
            /** @var User $target */
            $target = $row->user()->select(['id'])->firstOrFail();

            // ---------------------------
            // Reglas de HONOR
            // ---------------------------

            // 1) Asistencia: +10 al confirmar; -10 al desconfirmar (ambas idempotentes)
            if (array_key_exists('attended', $data)) {
                $nowAttended = (bool) $data['attended'];

                if ($nowAttended && !$prevAttended) {
                    $this->addHonorSafe(
                        $target,
                        +10,
                        HonorEvent::R_ATTEND_OK,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:attended"
                    );
                }

                if (!$nowAttended && $prevAttended) {
                    $this->addHonorSafe(
                        $target,
                        -10,
                        HonorEvent::R_ATTEND_UNDO,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:attended:undo"
                    );
                }
            }

            // 2) No show: -20 (solo si NO vino attended=1 en esta misma request)
            if (($data['no_show'] ?? false) && !($data['attended'] ?? false)) {
                $this->addHonorSafe(
                    $target,
                    -20,
                    HonorEvent::R_NO_SHOW,
                    ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                    "mesa:{$mesaId}:signup:{$signupId}:no_show"
                );
            }

            // 3) Comportamiento: transición entre good/regular/bad con undos correctos
            $newBehavior = (string) ($row->behavior ?? 'regular');
            if ($newBehavior !== $prevBehavior) {
                if ($newBehavior === 'good') {
                    if ($prevBehavior === 'bad') {
                        $this->addHonorSafe(
                            $target,
                            +10,
                            HonorEvent::R_BEHAV_UNDO_BAD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:bad"
                        );
                    }
                    $this->addHonorSafe(
                        $target,
                        +10,
                        HonorEvent::R_BEHAV_GOOD,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:behavior:good"
                    );

                } elseif ($newBehavior === 'bad') {
                    if ($prevBehavior === 'good') {
                        $this->addHonorSafe(
                            $target,
                            -10,
                            HonorEvent::R_BEHAV_UNDO_GOOD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:good"
                        );
                    }
                    $this->addHonorSafe(
                        $target,
                        -10,
                        HonorEvent::R_BEHAV_BAD,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:behavior:bad"
                    );

                } else { // regular
                    if ($prevBehavior === 'good') {
                        $this->addHonorSafe(
                            $target,
                            -10,
                            HonorEvent::R_BEHAV_UNDO_GOOD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:good"
                        );
                    } elseif ($prevBehavior === 'bad') {
                        $this->addHonorSafe(
                            $target,
                            +10,
                            HonorEvent::R_BEHAV_UNDO_BAD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:bad"
                        );
                    }
                }
            }
        });

        return back()->with('ok', 'Asistencia/comportamiento actualizados y honor aplicado.');
    }

    /**
     * Inserta honor de manera segura e idempotente por (user_id, slug).
     * Si el método addHonor existe en User, lo usa; si no, cae a firstOrCreate().
     */
    private function addHonorSafe(User $user, int $points, string $reason, array $meta, string $slug): void
    {
        if (method_exists($user, 'addHonor')) {
            $user->addHonor($points, $reason, $meta, $slug);
            return;
        }

        HonorEvent::firstOrCreate(
            ['user_id' => $user->id, 'slug' => $slug],
            ['points' => $points, 'reason' => $reason, 'meta' => $meta]
        );
    }
}
