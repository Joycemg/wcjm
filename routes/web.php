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

Route::pattern('mesa', '[0-9]+');
Route::pattern('signup', '[0-9]+');

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
 * ÁREA ADMIN (ability 'admin')
 * ========================= */
Route::middleware(array_merge($authVerified, ['admin.only:can:admin']))
    ->prefix('admin')->as('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('home');
        // agregá aquí otras rutas exclusivas de admin
    });

/* =========================
 * MESAS
 * ========================= */
Route::name('mesas.')->prefix('mesas')->group(function () use ($authVerified) {

    // Público
    Route::get('/', [GameTableController::class, 'index'])->name('index');
    Route::get('/{mesa}', [GameTableController::class, 'show'])->whereNumber('mesa')->name('show');

    // Gestión (solo autenticados; el controlador valida dueño/encargado/admin)
    Route::middleware($authVerified)->group(function () {
        Route::get('/create', [GameTableController::class, 'create'])->name('create');
        Route::post('/', [GameTableController::class, 'store'])->name('store');

        Route::get('/{mesa}/edit', [GameTableController::class, 'edit'])->whereNumber('mesa')->name('edit');
        Route::put('/{mesa}', [GameTableController::class, 'update'])->whereNumber('mesa')->name('update');
        Route::delete('/{mesa}', [GameTableController::class, 'destroy'])->whereNumber('mesa')->name('destroy');

        // Estado open/close — si querés exigir rol + permiso:
        // 'admin.only:mode:all,can:manage-tables,can:admin'
        Route::post('/{mesa}/open', [GameTableController::class, 'open'])->whereNumber('mesa')->name('open');
        Route::post('/{mesa}/close', [GameTableController::class, 'close'])->whereNumber('mesa')->name('close');

        // Notas privadas y estado del encargado
        Route::get('/{mesa}/notas', [GameTableController::class, 'notes'])->whereNumber('mesa')->name('notes');
        Route::put('/{mesa}/notas', [GameTableController::class, 'updateNotes'])->whereNumber('mesa')->name('notes.update');
        Route::post('/{mesa}/manager-playing', [GameTableController::class, 'updateManagerPlaying'])->whereNumber('mesa')->name('manager.playing');

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
        Route::post('/signups', [SignupController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::delete('/signups', [SignupController::class, 'destroy'])->middleware('throttle:30,1')->name('destroy');
    });

/* =========================
 * ASISTENCIA / HONOR
 * ========================= */
Route::scopeBindings()->group(function () use ($authVerified) {
    Route::post('/mesas/{mesa}/signups/{signup}/attendance', [AttendanceController::class, 'update'])
        ->whereNumber('mesa')->whereNumber('signup')
        ->middleware(array_merge($authVerified, ['admin.only:can:manage-tables']))
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
Route::get('/u/{user}', [ProfileController::class, 'show'])->name('profile.show');

require __DIR__ . '/auth.php';
