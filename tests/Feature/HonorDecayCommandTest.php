<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HonorDecayCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_user_honor_column(): void
    {
        Carbon::setTestNow('2025-01-15 12:00:00');

        $user = User::factory()->create([
            'created_at' => '2024-01-10 00:00:00',
            'updated_at' => '2024-01-10 00:00:00',
        ]);

        $this->artisan('honor:decay-inactivity', [
            '--period' => '2024-12',
        ])->assertExitCode(0);

        $user->refresh();

        $this->assertSame(-10, $user->honor);
        $this->assertSame(-10, (int) DB::table('users')->where('id', $user->id)->value('honor'));
        $this->assertDatabaseHas('honor_events', [
            'user_id' => $user->id,
            'slug' => 'decay:inactivity:2024-12',
            'points' => -10,
        ]);

        Carbon::setTestNow();
    }
}
