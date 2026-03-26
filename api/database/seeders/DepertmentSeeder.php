<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\RolesAndPermissions\Models\Depertment;
use Modules\Site\Models\Site;

class DepertmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $depertments = [
            [
                'name'        => 'Risk',
                'site_id' => '019bf99c-c3fb-7300-b144-e1c70e3de268',
            ],
            [
                'name'        => 'Legal and Compliance',
                'site_id' => '019bf99c-c3fb-7300-b144-e1c70e3de268',
            ],


        ];


        foreach ($depertments as $dpt) {
            Depertment::firstOrCreate(
                [
                    'name'     => $dpt['name'],
                    'site_id'    => $dpt['site_id'],
                ]
            );

        }
    }
}
