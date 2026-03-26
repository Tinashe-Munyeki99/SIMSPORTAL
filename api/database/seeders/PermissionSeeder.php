<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\RolesAndPermissions\Models\Permission;


class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $designations = [
            [
                'name' => 'create_user',
                'group' => 'Authentication',
                'display_name' => 'Create User',
            ],
      [
                'name' => 'delete_user',
                'group' => 'Authentication',
                'display_name' => 'delete User',
            ],

            [
                'name' => 'list_user',
                'group' => 'Authentication',
                'display_name' => 'list User',
            ],
            [
                'name' => 'update_user',
                'group' => 'Authentication',
                'display_name' => 'update User',
            ],

            [
                'name' => 'list_incident',
                'group' => 'Incident',
                'display_name' => 'list Incident',
            ],

            [
                'name' => 'create_incident',
                'group' => 'Incident',
                'display_name' => 'create Incident',
            ],
            [
                'name' => 'delete_incident',
                'group' => 'Incident',
                'display_name' => 'delete Incident',
            ],
            [
                'name' => 'update_incident',
                'group' => 'Incident',
                'display_name' => 'update Incident',
            ],

            [
                'name' => 'review_report',
                'group' => 'Report',
                'display_name' => 'review Report',
            ],

            [
                'name' => 'reject_issue',
                'group' => 'Report',
                'display_name' => 'report Issue',
            ],

            [
                'name' => 'manage_system_users',
                'group' => 'Authentication',
                'display_name' => 'manage system users',
            ],

            [
                'name' => 'create_role',
                'group' => 'Authentication',
                'display_name' => 'create role',
            ],

            [
                'name' => 'view_roles',
                'group' => 'Authentication',
                'display_name' => 'view role',
            ],
            [
                'name' => 'update_role',
                'group' => 'Authentication',
                'display_name' => 'update role',
            ],   [
                'name' => 'delete_role',
                'group' => 'Authentication',
                'display_name' => 'delete role',
            ],
    [
                'name' => 'assign_investigator',
                'group' => 'Report',
                'display_name' => 'report Investigator',
            ],    [
                'name' => 'escalate_incident',
                'group' => 'Report',
                'display_name' => 'escalate incident',
            ], [
                'name' => 'download_report',
                'group' => 'Report',
                'display_name' => 'download Report',
            ],[
                'name' => 'close_report',
                'group' => 'Report',
                'display_name' => 'close Report',
            ],[
                'name' => 'review_report',
                'group' => 'Report',
                'display_name' => 'review Report',
            ],[
                'name' => 'resolve_issue',
                'group' => 'Report',
                'display_name' => 'resolve Report',
            ],[
                'name' => 'reject_issue',
                'group' => 'Report',
                'display_name' => 'reject Report',
            ],

            [
                'name' => 'investigating_issue',
                'group' => 'Report',
                'display_name' => 'investigating Issue',
            ],
            [
                'name' => 'notify_incident',
                'group' => 'Notifications',
                'display_name' => 'Incident Notifications',
            ],




        ];

        foreach ($designations as $designation) {
            Permission::firstOrCreate(
                ['name' => $designation['name']],
                [
                    'group' => Str::slug($designation['group']),
                    'display_name' => $designation['display_name'],

                ]
            );
        }
    }
}
