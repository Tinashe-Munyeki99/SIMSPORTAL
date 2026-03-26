<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Authentication\Models\Brand;
use Modules\Site\Models\Site;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $sites = [
            [
                'brand_name'        => 'DAD',

            ],
    [
                'brand_name'        => 'PIZZA INN',

            ],    [
                'brand_name'        => 'CHICKEN INN',

            ],
            [
                'brand_name'        => 'BAKERS INN',

            ],
            [
                'brand_name'        => 'CREAMY INN',

            ],    [
                'brand_name'        => 'FISH INN',

            ] ,
            [
                'brand_name'        => 'GRAB & GO',

            ],
            [
                'brand_name'        => 'NANDOS',

            ],
     [
                'brand_name'        => 'GALITOS',

            ],
            [
                'brand_name'        => 'STEERS',

            ],
            [
                'brand_name'        => 'ROCO MAMAS',

            ],
            [
                'brand_name'        => 'OCEAN BASKET',

            ],
            [
                'brand_name'        => 'PASTINO',

            ],
            [
                'brand_name'        => 'HAEFELIS',

            ],


        ];


        foreach ($sites as $site) {
            Brand::firstOrCreate(
                ['name' => $site['brand_name']],

            );

        }
    }
}
