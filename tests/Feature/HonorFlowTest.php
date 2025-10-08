<?php

namespace Tests\Feature;

use App\Http\Controllers\AttendanceController;
use App\Models\GameTable;
use App\Models\Signup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HonorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconfirm_attendance_restores_positive_honor(): void
    {
        $manager = User::factory()->create();
        $player = User::factory()->create();

        $mesa = GameTable::create([
            'title' => 'Mesa de prueba',
            'capacity' => 5,
            'created_by' => $manager->id,
            'is_open' => true,
        ]);

        $signup = Signup::create([
            'game_table_id' => $mesa->id,
            'user_id' => $player->id,
            'is_counted' => true,
            'is_manager' => false,
            'attended' => false,
            'behavior' => 'regular',
        ]);

        $controller = app(AttendanceController::class);

        // Primera confirmación: +10
        $this->callAttendanceUpdate($controller, $manager, $mesa, $signup, ['attended' => true]);

        // Desconfirmar: agrega evento de undo (-10)
        $signup->refresh();
        $this->callAttendanceUpdate($controller, $manager, $mesa, $signup, ['attended' => false]);
        $this->assertDatabaseHas('honor_events', [
            'slug' => "mesa:{$mesa->id}:signup:{$signup->id}:attended:undo",
            'points' => -10,
        ]);

        // Reconirmar: debe limpiar el undo y dejar +10 neto
        $signup->refresh();
        $this->callAttendanceUpdate($controller, $manager, $mesa, $signup, ['attended' => true]);

        $this->assertDatabaseMissing('honor_events', [
            'slug' => "mesa:{$mesa->id}:signup:{$signup->id}:attended:undo",
        ]);

        $this->assertSame(10, $player->refresh()->refreshHonorAggregate());
    }

    public function test_behavior_good_clears_previous_undo(): void
    {
        $manager = User::factory()->create();
        $player = User::factory()->create();

        $mesa = GameTable::create([
            'title' => 'Mesa de conducta',
            'capacity' => 4,
            'created_by' => $manager->id,
            'is_open' => true,
        ]);

        $signup = Signup::create([
            'game_table_id' => $mesa->id,
            'user_id' => $player->id,
            'is_counted' => true,
            'is_manager' => false,
            'attended' => false,
            'behavior' => 'regular',
        ]);

        $controller = app(AttendanceController::class);

        // Marcar comportamiento bueno (+10)
        $this->callAttendanceUpdate($controller, $manager, $mesa, $signup, ['behavior' => 'good']);

        // Volver a regular crea undo (-10)
        $signup->refresh();
        $this->callAttendanceUpdate($controller, $manager, $mesa, $signup, ['behavior' => 'regular']);
        $undoSlug = "mesa:{$mesa->id}:signup:{$signup->id}:behavior:undo:good";
        $this->assertDatabaseHas('honor_events', [
            'slug' => $undoSlug,
            'points' => -10,
        ]);

        // Marcar de nuevo como good debe eliminar el undo y dejar el +10
        $signup->refresh();
        $this->callAttendanceUpdate($controller, $manager, $mesa, $signup, ['behavior' => 'good']);

        $this->assertDatabaseMissing('honor_events', ['slug' => $undoSlug]);
        $this->assertSame(10, $player->refresh()->refreshHonorAggregate());
    }

    public function test_quick_action_confirms_attendance_and_behavior(): void
    {
        $manager = User::factory()->create();
        $player = User::factory()->create();

        $mesa = GameTable::create([
            'title' => 'Mesa cerrada',
            'capacity' => 4,
            'created_by' => $manager->id,
            'is_open' => false,
        ]);

        $signup = Signup::create([
            'game_table_id' => $mesa->id,
            'user_id' => $player->id,
            'is_counted' => true,
            'is_manager' => false,
            'attended' => null,
            'behavior' => null,
        ]);

        $controller = app(AttendanceController::class);

        $this->callAttendanceUpdate($controller, $manager, $mesa, $signup, [
            'quick_action' => 'confirm_attend_good',
        ]);

        $signup->refresh();
        $this->assertTrue($signup->attended);
        $this->assertSame('good', $signup->behavior);
        $this->assertSame(20, $player->refresh()->refreshHonorAggregate());
    }

    private function callAttendanceUpdate(
        AttendanceController $controller,
        User $manager,
        GameTable $mesa,
        Signup $signup,
        array $payload
    ): void {
        $request = Request::create('/mesas/' . $mesa->id, 'POST', $payload);
        $request->setUserResolver(fn() => $manager);

        $controller->update($request, $mesa->fresh(), $signup->fresh());
    }
}

