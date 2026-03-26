<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Permission\Models\Permission;

class twinstackPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $permissions = [
            [
                'name'        => 'view_user',
                'description' => 'Permission to view user details'
            ],
            [
                'name'        => 'list_user',
                'description' => 'Permission to list user details'
            ],
            [
                'name'        => 'create_user',
                'description' => 'Permission to create new user'
            ],
            [
                'name'        => 'edit_user',
                'description' => 'Permission to edit warehouse details'
            ],
            [
                'name'        => 'delete_user',
                'description' => 'Permission to delete user'
            ],
            [
                'name'        => 'assign_warehouse_manager',
                'description' => 'Permission to assign a warehouse manager'
            ],
            [
                'name'        => 'create_company',
                'description' => 'Permission to create company'
            ],
            [
                'name'        => 'list_company',
                'description' => 'Permission to list company'
            ],
            [
                'name'        => 'view_company',
                'description' => 'Permission to view company'
            ],
            [
                'name'        => 'delete_company',
                'description' => 'Permission to delete company'
            ],
            [
                'name'        => 'edit_company',
                'description' => 'Permission to edit company'
            ],
            [
                'name'        => 'register_device',
                'description' => 'Permission to register device'
            ],
            [
                'name'        => 'list_device_status',
                'description' => 'Permission to check device'
            ],
            [
                'name'        => 'create_region',
                'description' => 'Permission to create region'
            ],
            [
                'name'        => 'list_region',
                'description' => 'Permission to create region'
            ],
            [
                'name'        => 'view_region',
                'description' => 'Permission to view region'
            ],
            [
                'name'        => 'delete_region',
                'description' => 'Permission to delete region'
            ],
            [
                'name'        => 'edit_region',
                'description' => 'Permission to edit region'
            ],
            [
                'name'        => 'manage_system_users',
                'description' => 'Permission to manage system users'
            ],

            [
                'name'        => 'user_change_password',
                'description' => 'Permission to change password'
            ],
            [
                'name'        => 'change_users_role',
                'description' => 'Permission to change users role'
            ],
            [
                'name'        => 'change_user_region_access',
                'description' => 'Permission to change user access role'
            ],
    [
                'name'        => 'list_region',
                'description' => 'Permission to list region'
            ],

            [
                'name'        => 'list_role',
                'description' => 'Permission to list roles'
            ],
            [
                'name'        => 'create_role',
                'description' => 'Permission to add new'
            ],
            [
                'name'        => 'edit_role',
                'description' => 'Permission to edit role'
            ],
            [
                'name'        => 'delete_role',
                'description' => 'Permission to delete role'
            ],
            [
                'name'        => 'list_device',
                'description' => 'Permission to list devices'
            ],
            [
                'name'        => 'ping_device',
                'description' => 'Permission to ping device'
            ],
            [
                'name'        => 'list_audit_status',
                'description' => 'Permission to list fiscal audit'
            ],
            [
                'name'        => 'get_device_configs',
                'description' => 'Permission to get device configurations'
            ],
            [
                'name'        => 'open_day',
                'description' => 'Permission to open fiscal day'
            ],
[
                'name'        => 'get_smtp_settings',
                'description' => 'Permission to get smtp settings'
            ],
            [
                'name'        => 'manage_smtp_settings',
                'description' => 'Permission to manage smtp settings'
            ], [
                'name'        => 'update_smtp_settings',
                'description' => 'Permission to save smtp settings'
            ], [
                'name'        => 'manage_smtp_settings',
                'description' => 'Permission to manage smtp settings'
            ],[
                'name'        => 'create_receipients',
                'description' => 'Permission to create email settings'
            ],[
                'name'        => 'enable_receipient',
                'description' => 'Permission to enable email to receive'
            ],[
                'name'        => 'delete_receipient',
                'description' => 'Permission to delete email'
            ],[
                'name'        => 'edit_receipient',
                'description' => 'Permission to edit email'
            ],[
                'name'        => 'list_permission',
                'description' => 'Permission to list permissions'
            ],[
                'name'        => 'show_role',
                'description' => 'Permission to show role permissions'
            ],[
                'name'        => 'create_role',
                'description' => 'Permission to create role'
            ],[
                'name'        => 'create_brand',
                'description' => 'Permission to create brand'
            ],[
                'name'        => 'delete_brand',
                'description' => 'Permission to delete brand'
            ],
             [
                'name'        => 'update_brand',
                'description' => 'Permission to update brand'
            ],[
                'name'        => 'list_brand',
                'description' => 'Permission to list brand'
            ],[
                'name'        => 'get_brand_by_device',
                'description' => 'Permission to device by brand'
            ],[
                'name'        => 'run_command',
                'description' => 'Permission to ru device audit command '
            ],[
                'name'        => 'count_live_devices',
                'description' => 'Permission to view live count'
            ],[
                'name'        => 'get_email_logs',
                'description' => 'Permission to get email logs'
            ],[
                'name'        => 'update_time',
                'description' => 'Permission to update time'
            ],[
                'name'        => 'access_settings',
                'description' => 'Permission to settings'
            ],[
                'name'        => 'access_user_management',
                'description' => 'Permission to user Management'
            ],
             [
                'name'        => 'view_log_viewer',
                'description' => 'Permission to log viewer'
            ], [
                'name'        => 'setup_time',
                'description' => 'Permission to setup time'
            ],[
                'name'        => 'device_schedules',
                'description' => 'Permission to device schedules'
            ],[
                'name'        => 'access_roles_permission',
                'description' => 'Permission to roles perm'
            ],
             [
                'name'        => 'access_brand_settings',
                'description' => 'Permission to brand settings'
            ],     [
                'name'        => 'access_integration_settings',
                'description' => 'Permission to integration settings'
            ],  [
                'name'        => 'access_company_settings',
                'description' => 'Permission to company settings'
            ], [
                'name'        => 'access_region_settings',
                'description' => 'Permission to region settings'
            ],[
                'name'        => 'access_cron_settings',
                'description' => 'Permission to cron settings'
            ],
[
                'name'        => 'access_smtp_settings',
                'description' => 'Permission to smtp settings'
            ],
[
                'name'        => 'access_email_settings',
                'description' => 'Permission to email settings'
            ],[
                'name'        => 'view_fiscal_receipts',
                'description' => 'Permission to view receipts'
            ],[
                'name'        => 'view_schedule',
                'description' => 'Permission to view schedule'
            ],
[
                'name'        => 'access_reports',
                'description' => 'Permission to access reports'
            ],

        ];


        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['permission_name' => $permission['name']],
                ['permission_description' => $permission['description']]
            );
        }
    }
}
