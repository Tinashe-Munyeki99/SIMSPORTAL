<?php

namespace App\Helpers;



use Illuminate\Support\Facades\DB;
use Modules\RolesAndPermissions\Models\Role;


class ResponseHelper
{
    /**
     * Generate a basic response.
     *
     * @param string $message
     * @param int $code
     * @param array|null $data
     * @return \Illuminate\Http\JsonResponse
     */
    public static function baseResponse(string $message, int $code = 200, ?array $data = null)
    {
        $response = [
            'code' => $code,
            'status' => $code === 200 ? 'success' : 'error',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Generate a template response.
     *
     * @param string $type
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function templateResponse(string $type, string $message, int $code)
    {
        $response = [
            'code' => $code,
            'status' => $type === 'success' ? 'success' : 'error',
            'message' => $message,
        ];

        return response()->json($response, $code);
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($role_id, $permission_name, $site_id = null, $customMessage = null)
    {
        // Try get school from middleware if not provided
        $site_id = $site_id ?? (app()->bound('site') ? app('site')->id : null);

        // 1) Fetch role globally first (no school filter)
        $role = Role::where('id', $role_id)
            ->where('site_id', $site_id)          // enforce tenant in the query
            ->with('rolePermissions.permission')
            ->first();

        if (!$role) {
            return response()->json([
                "code" => 422,
                "message" => "Role not found",
            ], 404);
        }

        if ($role->name === "Super Admin") {
            return true;
        }

        // 2) For all other roles, school context is required
        if (!$site_id) {
            return response()->json([
                "code" => 422,
                "message" => "School context not resolved",
            ], 400);
        }

        // 3) Ensure role belongs to this school
        if ($role->site_id !== $site_id) {
            return response()->json([
                "code" => 403,
                "message" => "Role does not belong to this school",
            ], 403);
        }

        // 4) Check permissions
        foreach ($role->rolePermissions as $rolePermission) {
            if ($rolePermission->permission?->name === $permission_name) {
                return true;
            }
        }

        return response()->json([
            "code" => 422,
            "message" => $customMessage ?? "User has no permission"
        ], 403);
    }
}


if (!function_exists('logSystemMessage')) {
    /**
     * Log a system message to the system_logs table.
     *
     * @param string $message The log message
     * @param array $context Optional context data
     * @return void
     */
    function logSystemMessage(string $message, array $context = []): void
    {
        DB::table('system_logs')->insert([
            'message'    => $message,
            'context'    => json_encode($context),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}


