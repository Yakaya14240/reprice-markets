<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'sort_order' => 10],
            ['code' => 'PLN', 'name' => 'Złoty polonais', 'symbol' => 'zł', 'sort_order' => 20],
            ['code' => 'GBP', 'name' => 'Livre sterling', 'symbol' => '£', 'sort_order' => 30],
            ['code' => 'CHF', 'name' => 'Franc suisse', 'symbol' => 'CHF', 'sort_order' => 40],
            ['code' => 'CZK', 'name' => 'Couronne tchèque', 'symbol' => 'Kč', 'sort_order' => 50],
            ['code' => 'HUF', 'name' => 'Forint hongrois', 'symbol' => 'Ft', 'sort_order' => 60],
            ['code' => 'RON', 'name' => 'Leu roumain', 'symbol' => 'lei', 'sort_order' => 70],
            ['code' => 'SEK', 'name' => 'Couronne suédoise', 'symbol' => 'kr', 'sort_order' => 80],
            ['code' => 'DKK', 'name' => 'Couronne danoise', 'symbol' => 'kr', 'sort_order' => 90],
            ['code' => 'NOK', 'name' => 'Couronne norvégienne', 'symbol' => 'kr', 'sort_order' => 100],
            ['code' => 'USD', 'name' => 'Dollar américain', 'symbol' => '$', 'sort_order' => 110],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'decimals' => 2,
                    'is_active' => true,
                    'sort_order' => $currency['sort_order'],
                ],
            );
        }
    }
}
