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

class GameTableAdminController extends Controller
{
    /**
     * Cierra una mesa de forma idempotente.
     * - Autoriza si puede 'update' O 'close'.
     * - Transacción + lockForUpdate para evitar carreras.
     * - Usa closeNow()/closeIfOpen() si existen; si no, fallback manual.
     * - Dispara GameTableClosed sólo si se cerró ahora.
     */
    public function close(Request $request, GameTable $mesa): RedirectResponse|JsonResponse
    {
        // Autoriza si NO tiene update ni close (si tiene alguna, pasa)
        if (Gate::denies('update', $mesa) && Gate::denies('close', $mesa)) {
            $this->authorize('close', $mesa); // forzar 403
        }

        try {
            $closed = DB::transaction(function () use ($mesa): bool {
                /** @var GameTable $row */
                $row = DatabaseUtils::applyPessimisticLock(GameTable::query())
                    ->findOrFail($mesa->getKey());

                // Preferir métodos de dominio si existen
                if (method_exists($row, 'closeNow')) {
                    $did = (bool) $row->closeNow();
                } elseif (method_exists($row, 'closeIfOpen')) {
                    $did = (bool) $row->closeIfOpen();
                } else {
                    // Fallback manual idempotente
                    $isOpen = (bool) ($row->is_open ?? false);
                    $isClosed = !is_null($row->closed_at);

                    if ($isOpen && !$isClosed) {
                        $row->closed_at = now();
                        $row->is_open = false;
                        $row->save();
                        $did = true;
                    } else {
                        $did = false;
                    }
                }

                if ($did) {
                    // 'rev' opcional: usa campo rev o updated_at (ms) si existe
                    $rev = $row->rev ?? ($row->updated_at?->valueOf() ?? now()->valueOf());

                    event(GameTableClosed::fromModel(
                        $row,
                        true,   // firstClose
                        null,   // signupsCount opcional
                        $rev    // rev opcional
                    ));
                }

                return $did;
            });

            if ($request->wantsJson()) {
                return $this->jsonOk(
                    ['closed' => $closed, 'table_id' => $mesa->id],
                    ['msg' => $closed ? 'Mesa cerrada y historial registrado.' : 'La mesa ya estaba cerrada.']
                );
            }

            return back()->with(
                $closed ? 'ok' : 'ok', // mantener una sola key de flash
                $closed ? 'Mesa cerrada y historial registrado.' : 'La mesa ya estaba cerrada.'
            );

        } catch (Throwable $e) {
            Log::error('Falló el cierre de mesa', [
                'table_id' => $mesa->id,
                'err' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return $this->jsonFail('No se pudo cerrar la mesa.', 500);
            }

            return back()->with('error', 'No se pudo cerrar la mesa.');
        }
    }
}
