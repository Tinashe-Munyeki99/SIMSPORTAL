<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Authentication\Models\Brand;
use Modules\Authentication\Models\Office;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $sites = [
            [
                'brand_name'        => 'IT',

            ],
            [
                'brand_name'        => 'HR',

            ],    [
                'brand_name'        => 'FINANCE',

            ],  [
                'brand_name'        => 'MARKETING',

            ],
            [
                'brand_name'        => 'PROJECTS',

            ],
            [
                'brand_name'        => 'CK',

            ],
            [
                'brand_name'        => 'CK DRY',

            ],    [
                'brand_name'        => 'CK WET',

            ]



        ];


        foreach ($sites as $site) {
            Office::firstOrCreate(
                ['name' => $site['brand_name']],

            );

        }
    }
}
