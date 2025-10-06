<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class HonorDemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->inRandomOrder()->take(20)->get()->each(function ($u) {
            $u->addHonor(rand(0, 200), 'Seeder');
            if (rand(0, 1))
                $u->addHonor(-rand(0, 20), 'Seeder ajuste negativo');
        });
    }
}
