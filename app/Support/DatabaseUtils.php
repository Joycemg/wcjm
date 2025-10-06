<?php declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Utilidades para manejar conexiones de base de datos en entornos compartidos.
 */
final class DatabaseUtils
{
    /** Drivers que no soportan SELECT ... FOR UPDATE. */
    private const LOCK_UNSUPPORTED_DRIVERS = ['sqlite', 'sqlsrv'];

    private function __construct()
    {
        // static class
    }

    /** Determina si la conexión soporta bloqueos pesimistas. */
    public static function supportsPessimisticLock(?ConnectionInterface $connection = null): bool
    {
        $connection ??= DB::connection();

        $driver = $connection->getDriverName();

        return !in_array($driver, self::LOCK_UNSUPPORTED_DRIVERS, true);
    }

    /**
     * Aplica lockForUpdate sólo si la conexión lo soporta.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    public static function applyPessimisticLock(Builder $query): Builder
    {
        $connection = $query->getModel()->getConnection();

        if (self::supportsPessimisticLock($connection)) {
            $query->lockForUpdate();
        }

        return $query;
    }
}
