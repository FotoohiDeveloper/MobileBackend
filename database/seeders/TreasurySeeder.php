<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TreasurySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $treasury = User::create([
            'first_name' => 'خزانه داری',
            'last_name' => '',
            'father_name' => '',
            'phone_number' => '07138303039',
            'is_verified' => true,
        ]);

        $treasury->createDefaultWallets(100000000, 1000000, 1000000, 1000000);
    }
}
