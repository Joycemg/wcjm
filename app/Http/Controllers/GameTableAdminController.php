<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\GameTableClosed;
use App\Models\GameTable;
use App\Support\DatabaseUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Acciones administrativas sobre mesas (cierre).
 * - Idempotente, transaccional y con bloqueo pesimista si el driver lo soporta.
 * - Dispara GameTableClosed sólo si se cerró en esta operación.
 */
final class GameTableAdminController extends Controller
{
    public function close(Request $request, GameTable $mesa): RedirectResponse|JsonResponse
    {
        // Autoriza si NO tiene update ni close (si tiene alguna, pasa)
        if (Gate::denies('update', $mesa) && Gate::denies('close', $mesa)) {
            $this->authorize('close', $mesa); // fuerza 403 con mensaje de Policy si aplica
        }

        try {
            $closed = DB::transaction(function () use ($mesa): bool {
                /** @var GameTable $row */
                $row = DatabaseUtils::applyPessimisticLock(GameTable::query())->findOrFail($mesa->getKey());

                // Usa métodos de dominio si existen
                if (method_exists($row, 'closeNow')) {
                    $did = (bool) $row->closeNow();
                } elseif (method_exists($row, 'closeIfOpen')) {
                    $did = (bool) $row->closeIfOpen();
                } else {
                    // Fallback manual: idempotente
                    $did = false;
                    if ((bool) ($row->is_open ?? false) === true && $row->closed_at === null) {
                        $row->forceFill(['is_open' => false, 'closed_at' => now()])->save();
                        $did = true;
                    }
                }

                if ($did) {
                    event(GameTableClosed::fromModel($row, true, null));
                }

                return $did;
            });

            if ($request->wantsJson()) {
                return $this->jsonOk(
                    ['closed' => $closed, 'table_id' => $mesa->id],
                    ['message' => $closed ? 'Mesa cerrada y historial registrado.' : 'La mesa ya estaba cerrada.']
                );
            }

            return back()->with('ok', $closed ? 'Mesa cerrada y historial registrado.' : 'La mesa ya estaba cerrada.');
        } catch (Throwable $e) {
            Log::error('Falló el cierre de mesa', [
                'table_id' => $mesa->id,
                'err' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return $this->jsonFail('No se pudo cerrar la mesa. Intentalo de nuevo.', 500);
            }

            return back()->with('error', 'No se pudo cerrar la mesa. Intentalo de nuevo.');
        }
    }
}
