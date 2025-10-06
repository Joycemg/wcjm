<?php declare(strict_types=1);

// app/Http/Controllers/MesaHonorController.php
namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\User;
use App\Services\HonorRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MesaHonorController extends Controller
{
    public function __construct(private HonorRules $rules)
    {
    }

    /**
     * Confirma asistencia (+10). Idempotente por slug.
     */
    public function confirmAttendance(Request $request, GameTable $mesa, Signup $signup): RedirectResponse
    {
        // Política de "gestión" de la mesa (dueño/manager/admin)
        $this->authorize('manage', $mesa);

        // Asegurar que el signup pertenece a la mesa (soporta mesa_id o game_table_id)
        $signupMesaId = (int) ($signup->getAttribute('game_table_id') ?? $signup->getAttribute('mesa_id'));
        abort_unless($signupMesaId === (int) $mesa->id, 404);

        /** @var User|null $auth */
        $auth = $request->user();
        abort_unless($auth instanceof User, 403);

        DB::transaction(function () use ($signup, $auth) {
            // Releer con lock para evitar carreras
            /** @var Signup $row */
            $row = Signup::query()->whereKey($signup->id)->lockForUpdate()->firstOrFail();

            if (!$row->attendance_confirmed_at) {
                $row->attendance_confirmed_at = now();
                $row->attendance_confirmed_by = $auth->id;
                // limpiar marca de no-show si la hubiera
                $row->no_show_at = null;
                $row->no_show_by = null;
                $row->save();
            }

            // Asigna honor (+10) — idempotente por slug
            $this->rules->confirmAttendance($row, $auth);
        });

        return back()->with('ok', 'Asistencia confirmada y puntos asignados (+10).');
    }

    /**
     * Marca no asistencia (−20). Idempotente por slug.
     */
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
            $row = Signup::query()->whereKey($signup->id)->lockForUpdate()->firstOrFail();

            if (!$row->no_show_at) {
                $row->no_show_at = now();
                $row->no_show_by = $auth->id;
                // si estaba confirmado por error, anulamos la marca (los puntos son idempotentes)
                $row->attendance_confirmed_at = null;
                $row->attendance_confirmed_by = null;
                $row->save();
            }

            // Asigna honor (−20) — idempotente por slug
            $this->rules->noShow($row, $auth);
        });

        return back()->with('ok', 'Marcado como no asistió (−20).');
    }

    /**
     * Registra comportamiento (good => +10, bad => −10). Idempotente por (mesa, signup, tipo, manager).
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

        // Idempotente por slug: mesa:..:signup:..:behavior:{type}:by:{manager_id}
        $this->rules->behavior($signup, $auth, $validated['type']);

        $msg = $validated['type'] === 'good'
            ? 'Buen comportamiento registrado (+10).'
            : 'Mal comportamiento registrado (−10).';

        return back()->with('ok', $msg);
    }
}
