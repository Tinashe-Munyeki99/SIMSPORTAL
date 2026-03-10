<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Authentication\Models\Brand;
use Modules\Authentication\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $sites = [
            [
                'brand_name'        => 'ZIMBABWE',

            ],
            [
                'brand_name'        => 'ESWATINI',

            ],    [
                'brand_name'        => 'KENYA',

            ],



        ];


        foreach ($sites as $site) {
            Country::firstOrCreate(
                ['name' => $site['brand_name']],

            );

        }
    }
}
