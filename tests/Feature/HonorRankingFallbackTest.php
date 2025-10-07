<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HonorRankingFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_uses_users_honor_when_events_table_missing(): void
    {
        Schema::dropIfExists('honor_events');

        $alice = User::factory()->create(['name' => 'Alice Test']);
        $bob = User::factory()->create(['name' => 'Bob Test']);
        $charlie = User::factory()->create(['name' => 'Charlie Test']);

        DB::table('users')->where('id', $alice->id)->update(['honor' => 40]);
        DB::table('users')->where('id', $bob->id)->update(['honor' => 120]);
        DB::table('users')->where('id', $charlie->id)->update(['honor' => 10]);

        $response = $this->actingAs($alice)->get(route('ranking.honor'));
        $response->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $users */
        $users = $response->viewData('users');

        $this->assertSame([
            $bob->id,
            $alice->id,
            $charlie->id,
        ], $users->pluck('id')->all());

        $this->assertSame([
            120,
            40,
            10,
        ], $users->pluck('honor_total')->map(fn ($v) => (int) $v)->all());

        $this->assertStringContainsString('#2', $response->getContent());
    }
}
