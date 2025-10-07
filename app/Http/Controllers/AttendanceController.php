<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\HonorEvent;
use App\Models\User;
use App\Support\DatabaseUtils;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;

/**
 * Actualiza asistencia/comportamiento de un signup y aplica honor correspondiente.
 * Reglas:
 *  - Sólo creador/manager/admin.
 *  - Idempotente por slug en honor_events.
 *  - Soporta columnas “legacy” si existen.
 */
final class AttendanceController extends Controller
{
    public function update(Request $request, GameTable $mesa, Signup $signup): RedirectResponse
    {
        /** @var User $actor */
        $actor = $this->requireUser($request);

        // Normaliza selects “_keep_”
        foreach (['attended', 'behavior', 'no_show'] as $k) {
            if ($request->input($k) === '_keep_') {
                $request->request->remove($k);
            }
        }

        // Permisos (creador/manager/admin)
        $isOwner = (int) ($mesa->created_by ?? 0) === (int) $actor->id;
        $isManager = (int) ($mesa->manager_id ?? 0) === (int) $actor->id;
        $isAdmin = (string) ($actor->role ?? '') === 'admin';
        abort_unless($isOwner || $isManager || $isAdmin, 403, 'No tenés permisos para modificar esta mesa.');

        // El signup debe pertenecer a la mesa
        abort_unless((int) $signup->game_table_id === (int) $mesa->id, 404, 'Inscripción no encontrada en esta mesa.');

        // Validación
        $data = $request->validate([
            'attended' => ['nullable', 'boolean'],
            'no_show' => ['nullable', 'boolean'],
            'behavior' => ['nullable', 'in:good,regular,bad'],
        ], [], [
            'attended' => 'asistencia',
            'no_show' => 'no show',
            'behavior' => 'comportamiento',
        ]);

        // Flags de esquema (compatibilidad)
        $hasAttendedColumn = $this->signupHasColumn('attended');
        $hasBehaviorColumn = $this->signupHasColumn('behavior');
        $hasLegacyConfirmedAt = $this->signupHasColumn('attendance_confirmed_at');
        $hasLegacyConfirmedBy = $this->signupHasColumn('attendance_confirmed_by');
        $hasLegacyNoShowAt = $this->signupHasColumn('no_show_at');
        $hasLegacyNoShowBy = $this->signupHasColumn('no_show_by');

        try {
            DB::transaction(function () use ($data, $mesa, $signup, $actor, $hasAttendedColumn, $hasBehaviorColumn, $hasLegacyConfirmedAt, $hasLegacyConfirmedBy, $hasLegacyNoShowAt, $hasLegacyNoShowBy) {
                // Bloqueo pesimista seguro en drivers que lo soporten
                /** @var Signup $row */
                $row = DatabaseUtils::applyPessimisticLock(
                    Signup::query()->whereKey($signup->id)
                )->firstOrFail();

                $prevAttended = $this->resolveAttendedState($row, $hasAttendedColumn, $hasLegacyConfirmedAt, $hasLegacyNoShowAt);
                $prevBehavior = $this->resolveBehaviorState($row, $hasBehaviorColumn);
                $dirty = false;
                $honorTouched = false;
                $nowStamp = null;

                // Persistencia de estados en Signup
                if (array_key_exists('attended', $data)) {
                    $nowAttended = (bool) $data['attended'];

                    if ($hasAttendedColumn && $row->attended !== $nowAttended) {
                        $row->attended = $nowAttended;
                        $dirty = true;
                    }
                    if ($hasLegacyConfirmedAt) {
                        $nowStamp ??= now();
                        $row->attendance_confirmed_at = $nowAttended ? $nowStamp : null;
                        $dirty = true;
                    }
                    if ($hasLegacyConfirmedBy && $row->attendance_confirmed_by !== ($nowAttended ? $actor->id : null)) {
                        $row->attendance_confirmed_by = $nowAttended ? $actor->id : null;
                        $dirty = true;
                    }
                    if ($nowAttended) {
                        if ($hasLegacyNoShowAt && $row->no_show_at !== null) {
                            $row->no_show_at = null;
                            $dirty = true;
                        }
                        if ($hasLegacyNoShowBy && $row->no_show_by !== null) {
                            $row->no_show_by = null;
                            $dirty = true;
                        }
                    }
                }

                if ($hasBehaviorColumn && array_key_exists('behavior', $data)) {
                    $newVal = (string) $data['behavior'];
                    if ((string) ($row->behavior ?? '') !== $newVal) {
                        $row->behavior = $newVal;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $row->save();
                }
                $dirty = false;

                $mesaId = (int) $mesa->id;
                $signupId = (int) $row->id;
                /** @var User $target */
                $target = $row->user()->select(['id'])->firstOrFail();
                $newBehavior = $this->resolveBehaviorState($row, $hasBehaviorColumn);

                // -------- HONOR: Asistencia (+10 / -10 undo) --------
                if (array_key_exists('attended', $data)) {
                    $nowAttended = (bool) $data['attended'];

                    if ($nowAttended && $prevAttended !== true) {
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            +10,
                            HonorEvent::R_ATTEND_OK,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $actor->id],
                            "mesa:{$mesaId}:signup:{$signupId}:attended"
                        ) || $honorTouched;

                        $honorTouched = $this->removeHonorEventSafe(
                            $target,
                            "mesa:{$mesaId}:signup:{$signupId}:attended:undo"
                        ) || $honorTouched;
                    }

                    if (!$nowAttended && $prevAttended === true) {
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            -10,
                            HonorEvent::R_ATTEND_UNDO,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $actor->id],
                            "mesa:{$mesaId}:signup:{$signupId}:attended:undo"
                        ) || $honorTouched;
                    }
                }

                // -------- HONOR: No show (-20) / remover penalización --------
                if (array_key_exists('no_show', $data)) {
                    $noShowSlug = "mesa:{$mesaId}:signup:{$signupId}:no_show";
                    $wantsNoShow = (bool) $data['no_show'];

                    if ($wantsNoShow && !($data['attended'] ?? false)) {
                        if ($hasLegacyNoShowAt || $hasLegacyNoShowBy) {
                            $nowStamp ??= now();
                            if ($hasLegacyNoShowAt) {
                                $row->no_show_at = $nowStamp;
                                $dirty = true;
                            }
                            if ($hasLegacyNoShowBy) {
                                $row->no_show_by = $actor->id;
                                $dirty = true;
                            }
                            if ($hasLegacyConfirmedAt && $row->attendance_confirmed_at !== null) {
                                $row->attendance_confirmed_at = null;
                                $dirty = true;
                            }
                            if ($hasLegacyConfirmedBy && $row->attendance_confirmed_by !== null) {
                                $row->attendance_confirmed_by = null;
                                $dirty = true;
                            }
                            if ($hasAttendedColumn && $row->attended !== false) {
                                $row->attended = false;
                                $dirty = true;
                            }
                            if ($dirty) {
                                $row->save();
                                $dirty = false;
                            }
                        }

                        $honorTouched = $this->addHonorSafe(
                            $target,
                            -20,
                            HonorEvent::R_NO_SHOW,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $actor->id],
                            $noShowSlug
                        ) || $honorTouched;
                    }

                    if (!$wantsNoShow) {
                        if ($hasLegacyNoShowAt && $row->no_show_at !== null) {
                            $row->no_show_at = null;
                            $dirty = true;
                        }
                        if ($hasLegacyNoShowBy && $row->no_show_by !== null) {
                            $row->no_show_by = null;
                            $dirty = true;
                        }
                        if ($dirty) {
                            $row->save();
                            $dirty = false;
                        }

                        $honorTouched = $this->removeHonorEventSafe($target, $noShowSlug) || $honorTouched;
                    }
                }

                // -------- HONOR: Comportamiento --------
                if ($hasBehaviorColumn && $newBehavior !== $prevBehavior) {
                    if ($newBehavior === 'good') {
                        if ($prevBehavior === 'bad') {
                            $honorTouched = $this->addHonorSafe(
                                $target,
                                +10,
                                HonorEvent::R_BEHAV_UNDO_BAD,
                                ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $actor->id],
                                "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:bad"
                            ) || $honorTouched;
                        }
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            +10,
                            HonorEvent::R_BEHAV_GOOD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $actor->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:good"
                        ) || $honorTouched;

                        $honorTouched = $this->removeHonorEventSafe(
                            $target,
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:good"
                        ) || $honorTouched;

                    } elseif ($newBehavior === 'bad') {
                        if ($prevBehavior === 'good') {
                            $honorTouched = $this->addHonorSafe(
                                $target,
                                -10,
                                HonorEvent::R_BEHAV_UNDO_GOOD,
                                ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $actor->id],
                                "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:good"
                            ) || $honorTouched;
                        }
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            -10,
                            HonorEvent::R_BEHAV_BAD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $actor->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:bad"
                        ) || $honorTouched;

                        $honorTouched = $this->removeHonorEventSafe(
                            $target,
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:bad"
                        ) || $honorTouched;

                    } // “regular” sólo aplica undos arriba (sin sumar/restar adicional)
                }

                if ($honorTouched && method_exists($target, 'refreshHonorAggregate')) {
                    $target->refreshHonorAggregate(true);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->with('err', 'No se pudo actualizar la asistencia/comportamiento. Intentalo de nuevo.');
        }

        return back()->with('ok', 'Asistencia/comportamiento actualizados y honor aplicado.');
    }

    // ----------------- Helpers privados -----------------

    private function addHonorSafe(User $user, int $points, string $reason, array $meta, string $slug): bool
    {
        $slug = trim($slug);
        if ($slug !== '' && \strlen($slug) > 191) {
            $slug = \substr($slug, 0, 191);
        }
        if ($slug === '') {
            $slug = null;
        }

        try {
            if (method_exists($user, 'addHonor')) {
                $event = $user->addHonor($points, $reason, $meta, $slug);
                return $event->wasRecentlyCreated;
            }

            $event = HonorEvent::firstOrCreate(
                ['user_id' => $user->id, 'slug' => $slug],
                ['points' => $points, 'reason' => $reason, 'meta' => $meta]
            );
            return $event->wasRecentlyCreated;
        } catch (QueryException $e) {
            if ($this->isMissingHonorTable($e)) {
                return false;
            }
            throw $e;
        }
    }

    private function removeHonorEventSafe(User $user, string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        try {
            if (method_exists($user, 'removeHonorEventBySlug')) {
                return $user->removeHonorEventBySlug($slug);
            }

            return HonorEvent::query()
                ->where('user_id', $user->id)
                ->where('slug', $slug)
                ->limit(1)
                ->delete() > 0;
        } catch (QueryException $e) {
            if ($this->isMissingHonorTable($e)) {
                return false;
            }
            throw $e;
        }
    }

    private function isMissingHonorTable(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');
        $exceptionCode = (string) $e->getCode();
        $message = strtolower((string) $e->getMessage());

        if (in_array($sqlState, ['42S02', '42P01'], true) || in_array($exceptionCode, ['42S02', '42P01'], true)) {
            return true;        // MySQL/MariaDB, PostgreSQL
        }
        if ($driverCode === '1146') {
            return true;        // MySQL/MariaDB table missing
        }
        if ($driverCode === '1' && str_contains($message, 'no such table')) {
            return true;        // SQLite
        }
        if (
            str_contains($message, 'honor_events') &&
            (str_contains($message, 'does not exist') || str_contains($message, "doesn't exist") || str_contains($message, 'not found'))
        ) {
            return true;        // Otros drivers
        }
        return false;
    }

    private function signupHasColumn(string $column): bool
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn((new Signup())->getTable(), $column);
        }
        return $cache[$column];
    }

    private function resolveAttendedState(Signup $signup, bool $hasAttendedColumn, bool $hasLegacyConfirmedAt, bool $hasLegacyNoShowAt): ?bool
    {
        if ($hasAttendedColumn) {
            $value = $signup->getAttribute('attended');
            return $value === null ? null : (bool) $value;
        }
        if ($hasLegacyConfirmedAt && $signup->getAttribute('attendance_confirmed_at')) {
            return true;
        }
        if ($hasLegacyNoShowAt && $signup->getAttribute('no_show_at')) {
            return false;
        }
        return null;
    }

    private function resolveBehaviorState(Signup $signup, bool $hasBehaviorColumn): string
    {
        if ($hasBehaviorColumn) {
            $value = $signup->getAttribute('behavior');
            return $value !== null ? (string) $value : 'regular';
        }
        return 'regular';
    }
}
