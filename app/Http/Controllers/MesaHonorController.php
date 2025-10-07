<?php declare(strict_types=1);

// app/Http/Controllers/MesaHonorController.php
namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\User;
use App\Services\HonorRules;
use App\Support\DatabaseUtils;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Acciones rápidas de honor sobre una mesa (asistencia/no-show/comportamiento).
 * - Idempotentes por slug (implementado en HonorRules).
 * - Lock pesimista para evitar carreras en shared hosting.
 */
final class MesaHonorController extends Controller
{
    public function __construct(private HonorRules $rules)
    {
    }

    /** Confirma asistencia (+10). Idempotente por slug. */
    public function confirmAttendance(Request $request, GameTable $mesa, Signup $signup): RedirectResponse
    {
        $this->authorize('manage', $mesa);

        // Garantiza que el signup corresponda a la mesa
        $signupMesaId = (int) ($signup->getAttribute('game_table_id') ?? $signup->getAttribute('mesa_id'));
        abort_unless($signupMesaId === (int) $mesa->id, 404);

        /** @var User|null $auth */
        $auth = $request->user();
        abort_unless($auth instanceof User, 403);

        DB::transaction(function () use ($signup, $auth) {
            /** @var Signup $row */
            $row = DatabaseUtils::applyPessimisticLock(
                Signup::query()->whereKey($signup->id)
            )->firstOrFail();

            if (!$row->attendance_confirmed_at) {
                $row->attendance_confirmed_at = now();
                $row->attendance_confirmed_by = $auth->id;
                // limpiar no-show si lo hubiera
                $row->no_show_at = null;
                $row->no_show_by = null;
                $row->save();
            }

            $this->rules->confirmAttendance($row, $auth); // +10 (idempotente)
        });

        return back()->with('ok', 'Asistencia confirmada y puntos asignados (+10).');
    }

    /** Marca no asistencia (−20). Idempotente por slug. */
    public function markNoShow(Request $request, GameTable $mesa, Signup $signup): RedirectResponse
    {
        $this->authorize('manage', $mesa);

        $signupMesaId = (int) ($signup->getAttribute('game_table_id') ?? $signup->getAttribute('mesa_id'));
        abort_unless($signupMesaId === (int) $mesa->id, 404);

        /** @var User|null $auth */
        $auth = $request->user();
        abort_unless($auth instanceof User, 403);

        DB::transaction(function () use ($signup, $auth) {
            /** @var Signup $row */
            $row = DatabaseUtils::applyPessimisticLock(
                Signup::query()->whereKey($signup->id)
            )->firstOrFail();

            if (!$row->no_show_at) {
                $row->no_show_at = now();
                $row->no_show_by = $auth->id;
                // si estaba confirmado por error, anulamos la marca (los puntos son idempotentes)
                $row->attendance_confirmed_at = null;
                $row->attendance_confirmed_by = null;
                $row->save();
            }

            $this->rules->noShow($row, $auth); // −20 (idempotente)
        });

        return back()->with('ok', 'Marcado como no asistió (−20).');
    }

    /**
     * Registra comportamiento:
     *   - good  => +10
     *   - bad   => −10
     *   - regular => no altera honor, pero los “undo” los maneja HonorRules entre transiciones.
     */
    public function behavior(Request $request, GameTable $mesa, Signup $signup): RedirectResponse
    {
        $this->authorize('manage', $mesa);

        $signupMesaId = (int) ($signup->getAttribute('game_table_id') ?? $signup->getAttribute('mesa_id'));
        abort_unless($signupMesaId === (int) $mesa->id, 404);

        $validated = $request->validate([
            'type' => 'required|string|in:good,bad',
        ]);

        /** @var User|null $auth */
        $auth = $request->user();
        abort_unless($auth instanceof User, 403);

        $this->rules->behavior($signup, $auth, $validated['type']);

        $msg = $validated['type'] === 'good'
            ? 'Buen comportamiento registrado (+10).'
            : 'Mal comportamiento registrado (−10).';

        return back()->with('ok', $msg);
    }
}
