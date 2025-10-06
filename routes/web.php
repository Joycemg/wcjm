<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    HomeController,
    DashboardController,
    GameTableController,
    SignupController,
    AttendanceController,
    ProfileController,
    HonorRankingController
};

/*
|--------------------------------------------------------------------------
| Web Routes (hostinger-friendly)
|--------------------------------------------------------------------------
| - Sin closures (mejor para route:cache).
| - `verified` es opcional (según config).
| - Throttle básico para acciones sensibles.
| - scopeBindings para anidar {signup} dentro de {mesa}.
*/

Route::pattern('mesa', '[0-9]+');
Route::pattern('signup', '[0-9]+');

/** Stack de auth con verificación opcional (si tu app no usa verificación, no se aplicará). */
$authVerified = array_values(array_filter([
    'auth',
    config('auth.require_email_verification', false) ? 'verified' : null,
]));

/* =========================
 * HOME & DASHBOARD
 * ========================= */
Route::get('/', HomeController::class)->name('home');

Route::middleware($authVerified)->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

/* =========================
 * MESAS
 * ========================= */
Route::name('mesas.')->prefix('mesas')->group(function () use ($authVerified) {

    Route::get('/', [GameTableController::class, 'index'])->name('index');
    Route::get('/{mesa}', [GameTableController::class, 'show'])->whereNumber('mesa')->name('show');

    // Crear/editar/actualizar/eliminar (protegidas)
    Route::middleware($authVerified)->group(function () {
        Route::get('/create', [GameTableController::class, 'create'])->name('create');
        Route::post('/', [GameTableController::class, 'store'])->name('store');

        Route::get('/{mesa}/edit', [GameTableController::class, 'edit'])->whereNumber('mesa')->name('edit');
        Route::put('/{mesa}', [GameTableController::class, 'update'])->whereNumber('mesa')->name('update');
        Route::delete('/{mesa}', [GameTableController::class, 'destroy'])->whereNumber('mesa')->name('destroy');

        // Acciones de estado (open/close)
        Route::post('/{mesa}/open', [GameTableController::class, 'open'])->whereNumber('mesa')->name('open');
        Route::post('/{mesa}/close', [GameTableController::class, 'close'])->whereNumber('mesa')->name('close');

        // Atajo "mi mesa"
        Route::get('/mine', [GameTableController::class, 'mine'])->name('mine');
    });
});

/* =========================
 * SIGNUPS (votar / retirar)
 * ========================= */
Route::name('signups.')->prefix('mesas/{mesa}')
    ->whereNumber('mesa')
    ->middleware($authVerified)
    ->group(function () {
        // Throttle defensivo (hostinger): 30 req/min por IP/usuario
        Route::post('/signups', [SignupController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::delete('/signups', [SignupController::class, 'destroy'])->middleware('throttle:30,1')->name('destroy');
    });

/* =========================
 * ASISTENCIA / COMPORTAMIENTO (HONOR)
 * scopeBindings garantiza que {signup} pertenezca a {mesa}
 * ========================= */
Route::scopeBindings()->group(function () use ($authVerified) {
    Route::post(
        '/mesas/{mesa}/signups/{signup}/attendance',
        [AttendanceController::class, 'update']
    )
        ->whereNumber('mesa')
        ->whereNumber('signup')
        ->middleware($authVerified)
        ->name('mesas.signups.attendance');
});

/* =========================
 * RANKING DE HONOR (público)
 * ========================= */
Route::get('/ranking/honor', HonorRankingController::class)->name('ranking.honor');

/* =========================
 * PERFIL
 * ========================= */
Route::middleware($authVerified)->group(function () {
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Perfil público por ID/binding
Route::get('/u/{user}', [ProfileController::class, 'show'])->whereNumber('user')->name('profile.show');
