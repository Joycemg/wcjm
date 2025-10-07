<?php

namespace Tests\Feature;

use App\Models\GameTable;
use App\Models\HonorEvent;
use App\Models\Signup;
use App\Models\User;
use App\Services\HonorRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HonorRulesAggregateTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_attendance_updates_user_honor_column(): void
    {
        $rules = app(HonorRules::class);

        $manager = User::factory()->create();
        $player = User::factory()->create();

        $mesa = GameTable::create([
            'title' => 'Mesa honor',
            'capacity' => 5,
            'created_by' => $manager->id,
            'is_open' => true,
        ]);

        $signup = Signup::create([
            'game_table_id' => $mesa->id,
            'user_id' => $player->id,
            'is_counted' => true,
            'is_manager' => false,
        ]);

        $rules->confirmAttendance($signup->fresh(), $manager);

        $player->refresh();

        $this->assertSame(10, $player->honor);
        $this->assertSame(10, (int) DB::table('users')->where('id', $player->id)->value('honor'));
    }

    public function test_reconfirming_attendance_clears_undo_and_refreshes_honor_column(): void
    {
        $rules = app(HonorRules::class);

        $manager = User::factory()->create();
        $player = User::factory()->create();

        $mesa = GameTable::create([
            'title' => 'Mesa undo',
            'capacity' => 4,
            'created_by' => $manager->id,
            'is_open' => true,
        ]);

        $signup = Signup::create([
            'game_table_id' => $mesa->id,
            'user_id' => $player->id,
            'is_counted' => true,
            'is_manager' => false,
        ]);

        $rules->confirmAttendance($signup->fresh(), $manager);
        $player->refresh();
        $this->assertSame(10, $player->honor);

        $slug = sprintf('mesa:%d:signup:%d:attended', $mesa->id, $signup->id);

        $player->addHonor(-10, HonorEvent::R_ATTEND_UNDO, [
            'mesa_id' => $mesa->id,
            'signup_id' => $signup->id,
            'by' => $manager->id,
        ], $slug . ':undo');
        $player->refreshHonorAggregate(true);
        $player->refresh();
        $this->assertSame(0, $player->honor);

        $rules->confirmAttendance($signup->fresh(), $manager);

        $player->refresh();
        $this->assertSame(10, $player->honor);
        $this->assertSame(10, (int) DB::table('users')->where('id', $player->id)->value('honor'));
        $this->assertDatabaseMissing('honor_events', ['slug' => $slug . ':undo']);
    }
}
