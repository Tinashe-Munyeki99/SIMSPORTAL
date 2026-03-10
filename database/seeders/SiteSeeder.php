<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Site\Models\Site;

class SiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $sites = [
            [
                'site_name'        => 'Simbisa Portal',
                'domain' => 'localhost',
                'host_ip' => '192.168.1.1',
                'host_port' => '80'
            ],


        ];


        foreach ($sites as $site) {
            Site::firstOrCreate(
                ['site_name' => $site['site_name']],
                [
                    'domain'     => $site['domain'],
                    'host_ip'    => $site['host_ip'],
                    'host_port'  => $site['host_port'],
                ]
            );

        }
    }
}
