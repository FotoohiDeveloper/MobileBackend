<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'United States Dollar', 'symbol' => '$', 'price' => 830000],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'price' => 930000],
            ['code' => 'GBP', 'name' => 'British Pound Sterling', 'symbol' => '£', 'price' => 1010000],
            ['code' => 'IRR', 'name' => 'Iranian Rial', 'symbol' => 'ریال', 'price' => 1],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
