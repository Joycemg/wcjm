<?php declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Utilidades para manejar conexiones de base de datos en entornos compartidos.
 *
 * - Aplica locks pesimistas o compartidos sólo si el driver los soporta.
 * - Opción de SKIP LOCKED cuando está disponible.
 * - Transacciones con reintentos ante deadlocks/lock timeouts.
 * - Forzar write PDO para evitar desincronización con réplicas.
 */
final class DatabaseUtils
{
    /** Drivers que NO soportan SELECT ... FOR UPDATE / FOR SHARE. */
    private const LOCK_UNSUPPORTED_DRIVERS = ['sqlite', 'sqlsrv'];

    /** Drivers que soportan SKIP LOCKED de forma nativa. */
    private const SKIP_LOCKED_DRIVERS = ['mysql', 'pgsql'];

    /** SQLSTATE / códigos típicos para reintentar en deadlocks/timeouts (MySQL/Postgres). */
    private const SQLSTATE_RETRYABLE = [
        '40001', // Serialization failure (PG, InnoDB)
        '40P01', // Deadlock detected (PG)
        '55P03', // Lock not available (PG)
        'HY000', // MySQL driver generic (a veces para deadlocks)
    ];
    private const MYSQL_RETRYABLE_ERRNO = [
        1205, // Lock wait timeout exceeded
        1213, // Deadlock found
    ];

    private function __construct()
    {
    }

    /* =========================================================
     * Capacidades por conexión
     * ========================================================= */

    public static function driver(?ConnectionInterface $connection = null): string
    {
        $connection ??= DB::connection();
        return $connection->getDriverName();
    }

    /** Determina si la conexión soporta bloqueos pesimistas (FOR UPDATE). */
    public static function supportsPessimisticLock(?ConnectionInterface $connection = null): bool
    {
        $driver = self::driver($connection);
        return !in_array($driver, self::LOCK_UNSUPPORTED_DRIVERS, true);
    }

    /** Determina si la conexión soporta shared locks (FOR SHARE / LOCK IN SHARE MODE). */
    public static function supportsSharedLock(?ConnectionInterface $connection = null): bool
    {
        $driver = self::driver($connection);
        return !in_array($driver, self::LOCK_UNSUPPORTED_DRIVERS, true);
    }

    /** Determina si la conexión soporta SKIP LOCKED. */
    public static function supportsSkipLocked(?ConnectionInterface $connection = null): bool
    {
        $driver = self::driver($connection);
        return in_array($driver, self::SKIP_LOCKED_DRIVERS, true);
    }

    /* =========================================================
     * Helpers de lock (se aplican sólo si corresponde)
     * ========================================================= */

    /**
     * Aplica lock FOR UPDATE si está soportado. Opcionalmente con SKIP LOCKED cuando sea posible.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @param  bool             $skipLocked  Si true, intenta "FOR UPDATE SKIP LOCKED" (MySQL8+/PG).
     * @return Builder<TModel>
     */
    public static function applyPessimisticLock(Builder $query, bool $skipLocked = false): Builder
    {
        $connection = $query->getModel()->getConnection();

        if (!self::supportsPessimisticLock($connection)) {
            return $query;
        }

        if ($skipLocked && self::supportsSkipLocked($connection)) {
            // Laravel permite lock() con string crudo
            return $query->lock('FOR UPDATE SKIP LOCKED');
        }

        return $query->lockForUpdate();
    }

    /**
     * Aplica shared lock si está soportado.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applySharedLock(Builder $query): Builder
    {
        $connection = $query->getModel()->getConnection();

        if (!self::supportsSharedLock($connection)) {
            return $query;
        }

        // En MySQL 8/PG esto equivale a FOR SHARE.
        return $query->sharedLock();
    }

    /**
     * Fuerza el uso de write PDO (evita leer desde réplicas desactualizadas).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function useWritePdo(Builder $query): Builder
    {
        $query->getQuery()->useWritePdo();
        return $query;
    }

    /* =========================================================
     * Transacciones con reintento (deadlock/timeout)
     * ========================================================= */

    /**
     * Ejecuta una transacción con reintentos automáticos ante deadlocks / lock timeouts.
     *
     * @template TReturn
     * @param  Closure():TReturn              $callback
     * @param  int                            $maxAttempts  Reintentos totales (incluye el 1er intento). Default: 3
     * @param  int                            $baseBackoffMs Tiempo base de backoff exponencial. Default: 100ms
     * @param  ConnectionInterface|null       $connection   Conexión (opcional). Default: DB::connection()
     * @return TReturn
     *
     * @throws \Throwable  Si excede los intentos o el error no es reintetable.
     */
    public static function transactionWithRetry(
        Closure $callback,
        int $maxAttempts = 3,
        int $baseBackoffMs = 100,
        ?ConnectionInterface $connection = null
    ) {
        $connection ??= DB::connection();

        $attempt = 0;

        beginning:
        $attempt++;

        try {
            return $connection->transaction($callback);
        } catch (QueryException $e) {
            if (!self::isRetryable($e) || $attempt >= $maxAttempts) {
                throw $e;
            }

            // Backoff exponencial con jitter leve (±20%)
            $exp = $baseBackoffMs * (2 ** ($attempt - 1));
            $jitter = (int) round($exp * (0.2 * (mt_rand(-100, 100) / 100)));
            $sleepMs = max(10, $exp + $jitter);
            usleep($sleepMs * 1000);

            goto beginning;
        }
    }

    /**
     * Determina si una QueryException es "reintetable" (deadlock/timeouts típicos).
     */
    private static function isRetryable(QueryException $e): bool
    {
        $sqlState = strtoupper((string) ($e->errorInfo[0] ?? ''));
        $driverErrno = (int) ($e->errorInfo[1] ?? 0);

        if (in_array($sqlState, self::SQLSTATE_RETRYABLE, true)) {
            return true;
        }

        // Errores específicos MySQL (errno)
        if (in_array($driverErrno, self::MYSQL_RETRYABLE_ERRNO, true)) {
            return true;
        }

        return false;
    }
}
