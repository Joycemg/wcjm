<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

class HonorDecayInactivity extends Command
{
    /**
     * Uso:
     *  php artisan honor:decay-inactivity
     *  php artisan honor:decay-inactivity --dry
     *  php artisan honor:decay-inactivity --period=2025-09
     *  php artisan honor:decay-inactivity --year=2025 --month=9
     *  php artisan honor:decay-inactivity --points=-10
     *  php artisan honor:decay-inactivity --tz=America/Argentina/Buenos_Aires
     */
    protected $signature = 'honor:decay-inactivity
        {--dry : Simular sin escribir}
        {--period= : Periodo YYYY-MM (prioridad sobre year/month)}
        {--year= : Año a procesar (YYYY)}
        {--month= : Mes a procesar (1-12)}
        {--points=-10 : Puntos a aplicar (negativos)}
        {--tz= : Timezone a usar para los límites del mes (default: config("app.timezone"))}';

    protected $description = 'Penaliza a usuarios sin actividad (signups is_counted=1) en el mes calendario indicado. Inserción masiva e idempotente.';

    public function handle(): int
    {
        // -------- 1) Resolver periodo (mes) --------
        $appTz = (string) ($this->option('tz') ?: config('app.timezone', 'UTC'));

        [$year, $month] = $this->resolvePeriod(
            (string) $this->option('period'),
            (string) $this->option('year'),
            (string) $this->option('month'),
            $appTz
        );

        $fromLocal = CarbonImmutable::createFromFormat('Y-n-j H:i:s', sprintf('%04d-%d-1 00:00:00', $year, $month), $appTz);
        $toLocal = $fromLocal->addMonth(); // [from, to)
        // Si guardás timestamps en UTC (lo más común en Laravel), normalizamos a UTC:
        $fromUtc = $fromLocal->utc();
        $toUtc = $toLocal->utc();

        $slug = sprintf('decay:inactivity:%04d-%02d', $year, $month);
        $dry = (bool) $this->option('dry');
        $points = (int) $this->option('points');

        // Razón: si existe constante en tu modelo, la tomamos; si no, string por defecto.
        $reasonConst = 'App\Models\HonorEvent::R_DECAY';
        $reason = \defined($reasonConst) ? \constant($reasonConst) : 'decay:inactivity';

        $metaJson = json_encode(['year' => $year, 'month' => $month], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->info(sprintf(
            'Evaluando inactividad %s a %s (tz=%s) → [UTC %s … %s) | slug=%s%s',
            $fromLocal->toDateTimeString(),
            $toLocal->toDateTimeString(),
            $appTz,
            $fromUtc->toDateTimeString(),
            $toUtc->toDateTimeString(),
            $slug,
            $dry ? ' [DRY]' : ''
        ));

        // -------- 2) Subconsulta: usuarios activos en el mes --------
        // signups is_counted=1 en el rango [fromUtc, toUtc)
        $activeSub = DB::table('signups')
            ->select('user_id')
            ->where('is_counted', 1)
            ->where('created_at', '>=', $fromUtc)
            ->where('created_at', '<', $toUtc)
            ->distinct();

        // -------- 3) Query base de "candidatos" inactivos e idempotencia --------
        // Candidatos: usuarios creados antes de fin de mes, sin actividad en el mes,
        // y que aún NO tengan un honor_event para este slug.
        $candidates = DB::table('users as u')
            ->leftJoinSub($activeSub, 's', 's.user_id', '=', 'u.id')
            ->leftJoin('honor_events as he', function ($j) use ($slug) {
                $j->on('he.user_id', '=', 'u.id')->where('he.slug', '=', $slug);
            })
            ->where('u.created_at', '<', $toUtc) // usuarios creados en el mes posterior no se penalizan
            ->whereNull('s.user_id')
            ->whereNull('he.user_id');

        // -------- 4) DRY-RUN: contar y mostrar muestras sin afectar DB --------
        if ($dry) {
            $count = (clone $candidates)->count('u.id');
            $this->line('Usuarios inactivos a penalizar: ' . $count);

            // Muestra hasta 50 IDs para inspección rápida
            if ($count > 0) {
                $sample = (clone $candidates)->limit(50)->pluck('u.id')->all();
                $rows = array_map(fn($id) => ['user_id' => $id], $sample);
                $this->table(['user_id (muestra máx 50)'], $rows);
            }
            return self::SUCCESS;
        }

        // -------- 5) Inserción MASIVA (INSERT … SELECT) idempotente --------
        // NOTA: Recomendado tener un índice/unique en honor_events (user_id, slug)
        // para garantizar idempotencia también a nivel DB (ver migración al final).
        $select = (clone $candidates)
            ->selectRaw('u.id as user_id')
            ->selectRaw('? as points', [$points])
            ->selectRaw('? as reason', [$reason])
            ->selectRaw('? as meta', [$metaJson])
            ->selectRaw('? as slug', [$slug])
            ->selectRaw('CURRENT_TIMESTAMP as created_at')
            ->selectRaw('CURRENT_TIMESTAMP as updated_at');

        $candidateIds = (clone $candidates)
            ->select('u.id as candidate_id')
            ->pluck('candidate_id')
            ->map(static fn($id) => (int) $id)
            ->all();

        $beforeCount = \count($candidateIds);

        if ($beforeCount === 0) {
            $this->info('No hay usuarios a penalizar. Listo.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($select) {
            DB::table('honor_events')->insertUsing(
                ['user_id', 'points', 'reason', 'meta', 'slug', 'created_at', 'updated_at'],
                $select
            );
        });

        $this->refreshUsersHonorAggregate($candidateIds);

        // Como insertUsing no siempre devuelve filas afectadas en todos los drivers,
        // reportamos el "planificado" ($beforeCount), que en condiciones normales coincide.
        $this->info("Penalizaciones aplicadas nuevas: {$beforeCount}");

        return self::SUCCESS;
    }

    /**
     * Resuelve el (año, mes) a procesar, con prioridad:
     *   --period (YYYY-MM) > --year/--month > mes anterior por defecto
     */
    private function resolvePeriod(string $periodOpt, string $yearOpt, string $monthOpt, string $tz): array
    {
        // --period=YYYY-MM
        if ($periodOpt !== '') {
            try {
                $p = CarbonImmutable::createFromFormat('Y-m', $periodOpt, $tz)->startOfMonth();
                return [$p->year, (int) $p->format('n')];
            } catch (InvalidFormatException) {
                $this->warn("Formato inválido en --period='{$periodOpt}', se usará fallback.");
            }
        }

        // --year + --month
        if ($yearOpt !== '' && $monthOpt !== '') {
            $y = (int) $yearOpt;
            $m = (int) $monthOpt;
            if ($y >= 1970 && $m >= 1 && $m <= 12) {
                return [$y, $m];
            }
            $this->warn("Parámetros --year/--month inválidos, se usará fallback.");
        }

        // Por defecto: mes anterior al actual (en tz dada)
        $now = CarbonImmutable::now($tz)->startOfMonth()->subMonthNoOverflow();
        return [$now->year, (int) $now->format('n')];
    }

    private function refreshUsersHonorAggregate(array $userIds): void
    {
        if ($userIds === [] || !Schema::hasColumn('users', 'honor')) {
            return;
        }

        $now = now();
        $subquery = 'SELECT COALESCE(SUM(points), 0) FROM honor_events WHERE honor_events.user_id = users.id';

        foreach (array_chunk($userIds, 500) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            DB::table('users')
                ->whereIn('id', $chunk)
                ->update([
                    'honor' => DB::raw("({$subquery})"),
                    'updated_at' => $now,
                ]);
        }
    }
}
