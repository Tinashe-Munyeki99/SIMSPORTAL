<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\RolesAndPermissions\Models\Depertment;
use Modules\RolesAndPermissions\Models\Designation;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $designations = [
            [
                'designation'        => 'Risk Admin',
                'site_id' => '019bf99c-c3fb-7300-b144-e1c70e3de268',
                'department_id' => '019c03a4-cd68-72fd-9671-7324c5459a86',
            ],
            [
                'designation'        => 'Risk General User',
                'site_id' => '019bf99c-c3fb-7300-b144-e1c70e3de268',
                'department_id' => '019c03a4-cd68-72fd-9671-7324c5459a86',
            ],
            [
                'designation'        => 'Legal Admin',
                'site_id' => '019bf99c-c3fb-7300-b144-e1c70e3de268',
                'department_id' => '019c03a4-cda9-7066-bb17-5676e05b6fe5',
            ],


        ];


        foreach ($designations as $dsg) {
            Designation::firstOrCreate(
                [
                    'designation'     => $dsg['designation'],
                    'site_id'    => $dsg['site_id'],
                    'department_id'    => $dsg['department_id'],
                ]
            );

        }
    }
}
