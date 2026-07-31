<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Main church + Head Pastor ---
        $main = Church::create([
            'name' => 'Shepherd Jubilee Church Inc. (Main)',
            'is_main' => true,
        ]);

        $headPastor = User::create([
            'name' => 'Head Pastor',
            'email' => 'headpastor@sjci.test',
            'password' => 'password',
            'role' => UserRole::HeadPastor,
            'church_id' => $main->id,
        ]);

        $main->update(['pastor_id' => $headPastor->id]);

        // --- Two outreach churches, each with one Outreach Pastor ---
        foreach ([1, 2] as $n) {
            $outreach = Church::create([
                'name' => "Outreach Church {$n}",
                'is_main' => false,
            ]);

            $pastor = User::create([
                'name' => "Outreach Pastor {$n}",
                'email' => "outreach{$n}@sjci.test",
                'password' => 'password',
                'role' => UserRole::OutreachPastor,
                'church_id' => $outreach->id,
            ]);

            $outreach->update(['pastor_id' => $pastor->id]);
        }
    }
}
