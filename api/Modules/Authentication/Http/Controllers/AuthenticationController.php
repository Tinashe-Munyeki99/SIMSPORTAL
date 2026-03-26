<?php

namespace Modules\Authentication\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Authentication\Models\Brand;
use Modules\Authentication\Models\BrandOfficeManagement;
use Modules\Authentication\Models\Country;
use Modules\Authentication\Models\Office;
use Modules\Authentication\Models\OtherUserInfo;
use Modules\Authentication\Models\SystemUser;

use Modules\RolesAndPermissions\Models\Depertment;
use Modules\RolesAndPermissions\Models\Designation;
use Modules\RolesAndPermissions\Models\Permission;
use Modules\RolesAndPermissions\Models\Role;
use Modules\RolesAndPermissions\Models\RolePermission;
use function App\Helpers\hasPermission;

class AuthenticationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
//    public function registerUser(Request $request)
//    {
//        $site = app('site');
//
////        $result = hasPermission(auth()->user()->role_id, 'create_user', $site->id);
////        if ($result !== true) {
////            return $result;
////        }
//
//        $validator = Validator::make($request->toArray(),[
//            'full_name' => 'required|string|max:255',
//            'last_name' => 'nullable|string|max:255',
//            'email' => 'required|string|email|max:255|unique:system_users,email',
//            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
//            'role_id' => 'required|integer|exists:roles,id',
//            'designation_id' => 'required|integer|exists:designations,id',
//            'department_id' => 'required|integer|exists:departments,id',
//        ]);
//
//        if ($validator->fails()) {
//            return response()->json([
//                "status"  => 422,
//                "message" => "Validation error",
//                "data"    => $validator->errors()
//            ], 422);
//        }
//
//        try {
//            $newSystemUser = DB::transaction(function () use ($validated, $site) {
//                $user = new SystemUser();
//                $user->full_name = $validated['full_name'];
//                $user->last_name = $validated['last_name'] ?? null;
//                $user->email = $validated['email'];
//                $user->password = Hash::make($validated['password']);
//                $user->role_id = $validated['role_id'];
//                $user->designation_id = $validated['designation_id'];
//                $user->department_id = $validated['department_id'];
//                $user->site_id = $site->id;
//                $user->save();
//
//                return $user;
//            });
//
//            $token = $newSystemUser->createToken($newSystemUser->email . 'Auth-Token')->plainTextToken;
//
//            return response()->json([
//                'message' => 'Registration successful',
//                'token_type' => 'Bearer',
//                'token' => $token,
//                'user' => $newSystemUser->makeHidden(['password']), // or rely on $hidden in model
//            ], 200);
//
//        } catch (\Throwable $e) {
//            return response()->json([
//                'message' => 'Something went wrong while registering the user.',
//                'error' => $e->getMessage(),
//            ], 500);
//        }
//    }
    public function registerUser(Request $request)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'create_user', $site->id);
        if ($result !== true) {
            return $result;
        }

        // ✅ validate WITHOUT password (we generate it)
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',

            'email' => [
                'required', 'string', 'email', 'max:255',
                // unique per site (same pattern as selfRegisterUser)
                Rule::unique('system_users', 'email')->where(fn ($q) => $q->where('site_id', $site->id)),
            ],

            'role_id' => 'required|string|exists:roles,id',
            'designation_id' => 'required|string|exists:designations,id',
            'department_id' => 'required|string|exists:depertments,id', // ⚠️ check spelling

            'multicountry_viewer' => 'required|boolean',

            // if NOT multicountry => country required, else nullable
            'country_id' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn () => (bool)$request->multicountry_viewer === false),
                'exists:countries,id',
            ],

            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['uuid', 'exists:brands,id'],

            'office_ids' => ['nullable', 'array'],
            'office_ids.*' => ['uuid', 'exists:offices,id'],

            // If you also collect these in admin create-user UI
            'phone' => 'nullable|string|max:30',
            'position' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'shop_name' => 'nullable|string|max:255',
        ]);

        // ✅ enforce: must choose at least one brand or office
        $validator->after(function ($v) use ($request) {
            $brands = (array) $request->input('brand_ids', []);
            $offices = (array) $request->input('office_ids', []);

            if (count($brands) === 0 && count($offices) === 0) {
                $v->errors()->add('brand_ids', 'Select at least one brand or office.');
            }

            if ((bool)$request->multicountry_viewer === false && !$request->filled('country_id')) {
                $v->errors()->add('country_id', 'Country is required for non-multicountry users.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                "status"  => 422,
                "message" => "Validation error",
                "data"    => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // ✅ OPTIONAL: domain restriction (same as selfRegisterUser)
        $allowedDomains = ['fisctrack.com', 'simbisa.co.zw', 'zw-simbisa.com', 'simbisabrands.com', 'simbisa.com'];
        $emailDomain = strtolower(substr(strrchr($validated['email'], "@"), 1));
        if (!in_array($emailDomain, $allowedDomains, true)) {
            return response()->json([
                'status' => 403,
                'message' => "Registration restricted to authorized domains only. email domain '$emailDomain' is not allowed.",
                'error' => "Email domain '$emailDomain' is not allowed.",
            ], 403);
        }

        try {
            // ✅ generate password and email it
            $plainPassword = Str::password(10);

            $newSystemUser = DB::transaction(function () use ($validated, $site, $plainPassword) {
                $user = new SystemUser();
                $user->full_name = $validated['full_name'];
                $user->email = $validated['email'];

                // generated password
                $user->password = Hash::make($plainPassword);

                $user->role_id = $validated['role_id'];
                $user->designation_id = $validated['designation_id'];
                $user->department_id = $validated['department_id'];
                $user->site_id = $site->id;



                $user->save();

                // Optional other-info update (only if your table exists + you want it)
                // If you already have a proper create flow, keep it. Otherwise:
                if (
                    array_key_exists('phone', $validated) ||
                    array_key_exists('position', $validated) ||
                    array_key_exists('city', $validated) ||
                    array_key_exists('country_id', $validated) ||
                    array_key_exists('shop_name', $validated)
                ) {
                    // create or update OtherUserInfo if you have it
                    $other = OtherUserInfo::firstOrNew(['user_id' => $user->id]);

                    if (isset($validated['phone'])) $other->phone = $validated['phone'];
                    if (isset($validated['position'])) $other->position = $validated['position'] ?? null;
                    if (isset($validated['city'])) $other->city = $validated['city'] ?? null;

// ✅ assign UUID
                    if (array_key_exists('country_id', $validated)) {
                        $other->country = $validated['country_id'];
                    }

                    if (isset($validated['shop_name'])) $other->shop_name = $validated['shop_name'] ?? null;

                    $other->save();
                }

                // ✅ BrandOfficeManagement mapping
                $isMulti = (bool)($validated['multicountry_viewer'] ?? false);
                $countryId = $isMulti ? null : ($validated['country_id'] ?? null);

                $brandIds = $validated['brand_ids'] ?? [];
                $officeIds = $validated['office_ids'] ?? [];

                $rows = [];

                foreach ($brandIds as $bid) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'brand_id' => $bid,
                        'office_id' => null,
                        'country_id' => $countryId,
                    ];
                }

                foreach ($officeIds as $oid) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'brand_id' => null,
                        'office_id' => $oid,
                        'country_id' => $countryId,
                    ];
                }

                $unique = collect($rows)->unique(fn ($r) =>
                    ($r['user_id'] ?? '') . '|' . ($r['brand_id'] ?? '') . '|' . ($r['office_id'] ?? '') . '|' . ($r['country_id'] ?? '')
                )->values()->all();

                if (count($unique)) {
                    BrandOfficeManagement::insert($unique);
                }

                return $user;
            });

            // ✅ send email with generated password
            $subject = "Welcome to Simbisa Portal — Your Account Details";
            $body = view('authentication::welcom', [
                'user' => $newSystemUser,
                'password' => $plainPassword,
            ])->render();

            $this->sendGraphEmail(
                env('MSGRAPH_FROM_ADDRESS'),
                $newSystemUser->email,
                $subject,
                $body
            );

            // Optional: issue token or not (you did in selfRegisterUser)
            $token = $newSystemUser->createToken($newSystemUser->email . 'Auth-Token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful. Please check your email for login credentials.',
                'token_type' => 'Bearer',
                'token' => $token,
                'user' => $newSystemUser->makeHidden(['password']),
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong while registering the user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function updateUser(Request $request, string $id)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'update_user', $site->id);
        if ($result !== true) {
            return $result;
        }

        $user = SystemUser::where('site_id', $site->id)->where('id', $id)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',

            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('system_users', 'email')
                    ->where(fn ($q) => $q->where('site_id', $site->id))
                    ->ignore($user->id, 'id'),
            ],

            'role_id' => 'required|string|exists:roles,id',
            'designation_id' => 'required|string|exists:designations,id',
            'department_id' => 'required|string|exists:depertments,id', // ⚠️ verify table name

            'multicountry_viewer' => 'required|boolean',

            'country_id' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn () => (bool)$request->multicountry_viewer === false),
                'exists:countries,id',
            ],

            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['uuid', 'exists:brands,id'],

            'office_ids' => ['nullable', 'array'],
            'office_ids.*' => ['uuid', 'exists:offices,id'],

            'phone' => 'nullable|string|max:30',
            'position' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255', // (you don't actually use this in other_info)
            'shop_name' => 'nullable|string|max:255',
        ]);

        $validator->after(function ($v) use ($request) {
            $brands = (array) $request->input('brand_ids', []);
            $offices = (array) $request->input('office_ids', []);

            if (count($brands) === 0 && count($offices) === 0) {
                $v->errors()->add('brand_ids', 'Select at least one brand or office.');
            }

            if ((bool)$request->multicountry_viewer === false && !$request->filled('country_id')) {
                $v->errors()->add('country_id', 'Country is required for non-multicountry users.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                "status"  => 422,
                "message" => "Validation error",
                "data"    => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Optional: same allowed domain restriction as registerUser
        $allowedDomains = ['fisctrack.com', 'simbisa.co.zw', 'zw-simbisa.com', 'simbisabrands.com', 'simbisa.com'];
        $emailDomain = strtolower(substr(strrchr($validated['email'], "@"), 1));
        if (!in_array($emailDomain, $allowedDomains, true)) {
            return response()->json([
                'status' => 403,
                'message' => "Registration restricted to authorized domains only. email domain '$emailDomain' is not allowed.",
                'error' => "Email domain '$emailDomain' is not allowed.",
            ], 403);
        }

        try {
            DB::transaction(function () use ($validated, $site, $user) {
                // update base user
                $user->full_name = $validated['full_name'];
                $user->email = $validated['email'];
                $user->role_id = $validated['role_id'];
                $user->designation_id = $validated['designation_id'];
                $user->department_id = $validated['department_id'];
                $user->site_id = $site->id;



                $user->save();

                // update other info
                $other = OtherUserInfo::firstOrNew(['user_id' => $user->id]);

                if (array_key_exists('phone', $validated)) $other->phone = $validated['phone'] ?? null;
                if (array_key_exists('position', $validated)) $other->position = $validated['position'] ?? null;
                if (array_key_exists('city', $validated)) $other->city = $validated['city'] ?? null;
                if (array_key_exists('shop_name', $validated)) $other->shop_name = $validated['shop_name'] ?? null;

                // store UUID in other_info.country
                if (array_key_exists('country_id', $validated)) {
                    $other->country = $validated['country_id'] ?? null;
                }

                $other->save();

                // replace brand/office mapping
                $isMulti = (bool)($validated['multicountry_viewer'] ?? false);
                $countryId = $isMulti ? null : ($validated['country_id'] ?? null);

                $brandIds = $validated['brand_ids'] ?? [];
                $officeIds = $validated['office_ids'] ?? [];

                // wipe existing rows for this user
                BrandOfficeManagement::where('user_id', $user->id)->delete();

                $rows = [];

                foreach ($brandIds as $bid) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'brand_id' => $bid,
                        'office_id' => null,
                        'country_id' => $countryId,
                    ];
                }

                foreach ($officeIds as $oid) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'brand_id' => null,
                        'office_id' => $oid,
                        'country_id' => $countryId,
                    ];
                }

                $unique = collect($rows)->unique(fn ($r) =>
                    ($r['user_id'] ?? '') . '|' . ($r['brand_id'] ?? '') . '|' . ($r['office_id'] ?? '') . '|' . ($r['country_id'] ?? '')
                )->values()->all();

                if (count($unique)) {
                    BrandOfficeManagement::insert($unique);
                }
            });

            $fresh = SystemUser::with([
                "brandOffice" => fn ($q) => $q->with(["brand", "office"]),
                "otherInfo",
                "department",
                "role",
                "designation",
            ])->where('site_id', $site->id)->where('id', $id)->first();

            return response()->json([
                'message' => 'User updated successfully.',
                'user' => $fresh?->makeHidden(['password']),
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong while updating the user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteUser(Request $request, string $id)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'delete_user', $site->id);
        if ($result !== true) {
            return $result;
        }

        $user = SystemUser::where('site_id', $site->id)->where('id', $id)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Optional: prevent deleting yourself
        if ((string)auth()->id() === (string)$user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($user) {
                // remove access mappings
                BrandOfficeManagement::where('user_id', $user->id)->delete();

                // optional: soft delete other info (if it uses SoftDeletes)
                if (class_uses(OtherUserInfo::class, SoftDeletes::class)) {
                    OtherUserInfo::where('user_id', $user->id)->delete();
                } else {
                    // if not softdeletes and you want to remove
                    // OtherUserInfo::where('user_id', $user->id)->delete();
                }

                // soft delete user
                $user->delete();
            });

            return response()->json([
                'message' => 'User deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong while deleting the user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function selfRegisterUser(Request $request)
    {
        $site = app('site');

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',


            'email' => [
                'required', 'string', 'email', 'max:255',
                // Option A (global unique):
                // 'unique:system_users,email',

                // Option B (unique per site):
                Rule::unique('system_users', 'email')->where(fn($q) => $q->where('site_id', $site->id)),
            ],

            'phone' => 'required|string|max:30',
            'position' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'brand_id' => 'required',
            'shop_name' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $email = $validated['email'];

        $allowedDomains = [
            'fisctrack.com', 'simbisa.co.zw', 'zw-simbisa.com', 'simbisabrands.com', 'simbisa.com'
        ];
        $emailDomain = strtolower(substr(strrchr($email, "@"), 1));

        if (!in_array($emailDomain, $allowedDomains, true)) {
            return response()->json([
                'status' => 403,
                'message' => "Registration restricted to authorized domains only. email domain '$emailDomain' is not allowed.",
                'error' => "Email domain '$emailDomain' is not allowed.",
            ], 403);
        }

        try {
            $plainPassword = Str::password(10);

            $newSystemUser = DB::transaction(function () use ($validated, $site, $email, $plainPassword) {
                $user = new SystemUser();
                $user->full_name = $validated['full_name'];

                $user->email = $email;
                $user->password = Hash::make($plainPassword);

                // Defaults for self-registration
                $user->role_id = "820c763c-fc35-11f0-b863-5cb47e66ef03";
                $user->designation_id = "019c03ad-fcc8-724a-978f-8228b330b833";
                $user->department_id = "019c03a4-cd68-72fd-9671-7324c5459a86";

                $user->site_id = $site->id;
                $user->save();

                $other = new OtherUserInfo();
                $other->user_id = $user->id;
                $other->phone = $validated['phone'];
                $other->position = $validated['position'];
                $other->city = $validated['city'];
                $other->country = $validated['country'];
                $other->brand_id = $validated['brand_id'];
                $other->shop_name = $validated['shop_name'];
                $other->save();

                return $user;
            });

            $subject = "Welcome to Simbisa Portal — Your Account Details";
            $body = view('authentication::welcom', [
                'user' => $newSystemUser,
                'password' => $plainPassword,
            ])->render();

            $this->sendGraphEmail(
                env('MSGRAPH_FROM_ADDRESS'),
                $newSystemUser->email,
                $subject,
                $body
            );

            // Optional: don’t issue token here; require login using emailed password.
            $token = $newSystemUser->createToken($newSystemUser->email . 'Auth-Token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful. Please check your email for login credentials.',
                'token_type' => 'Bearer',
                'token' => $token,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong while registering the user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    private function sendGraphEmail(string $fromUser, string $toEmail, string $subject, string $body)
    {
        try {
            $accessToken = $this->getAccessToken();
            $fromEmail   = $fromUser; // must exist in your Microsoft 365 tenant

            $payload = [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => 'HTML', // ✅ very important for HTML formatting
                        'content' => $body,      // ✅ do NOT escape or nl2br
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => ['address' => $toEmail],
                        ],
                    ],
                ],
                'saveToSentItems' => true,
            ];

            // Log the outgoing body (optional for debugging)
            Log::info("Sending Graph email to {$toEmail}");

            $response = Http::withToken($accessToken)
                ->post("https://graph.microsoft.com/v1.0/users/{$fromEmail}/sendMail", $payload);

            Log::info('Graph API sendMail response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            if ($response->failed()) {
                throw new \Exception("Graph API error: " . $response->body());
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Microsoft Graph email failed: " . $e->getMessage());
            return false;
        }
    }


    private function getAccessToken()
    {
        $tenantId = env('MSGRAPH_TENANT_ID');
        $clientId = env('MSGRAPH_CLIENT_ID');
        $clientSecret = env('MSGRAPH_CLIENT_SECRET');

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        $response = Http::asForm()->post($url, [
            'client_id' => $clientId,
            'scope' => 'https://graph.microsoft.com/.default',
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            throw new \Exception("Failed to get access token: " . $response->body());
        }

        return $response->json()['access_token'];
    }


    public function login(Request $request)
    {
        $site = app('site');

        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $user = SystemUser::where('email', $request->email)
            ->where('site_id', $site->id)
            ->with([
                'otherInfo' => function ($query) {
                    $query->with(['country','brand']);
                },
                'department',
                'designation',
                'role',
                'brandOffice'=> function ($query) {
                $query->with(['brand','office']);
                }
            ])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid email or password',
            ], 401);
        }

        // revoke old tokens (only if you want "1 device/session at a time")
        $user->tokens()->delete();

        $tokenName = $user->email . '-Auth-Token';
        // optional abilities: ['*'] or ['fees:read', 'fees:write']
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'status'     => 'success',
            'message'    => 'Login successful',
            'token_type' => 'Bearer',
            'token'      => $token,
            'user'       => $user->makeHidden(['password']),
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices',
        ], 200);
    }


    public function changePasswordReset(Request $request)
    {


        $validator = Validator::make($request->all(), [
            "email"    => "required|email|exists:system_users,email",
            "old_password" => "required",
            "password" => ["required", "confirmed", Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status"  => 422,
                "message" => "Validation error",
                "data"    => $validator->errors()
            ], 422);
        }

        $selectUser = SystemUser::where("email", $request->email)->first();

        if (!$selectUser || !Hash::check($request->old_password, $selectUser->password)) {
            return ResponseHelper::templateResponse("error", "Old password is incorrect.", 422);
        }

        $selectUser->password = Hash::make($request->password);
        $selectUser->save();

        return ResponseHelper::templateResponse("success", "Password changed successfully.", 200);
    }

    public function listUsers(Request $request)
    {
        $authUser = auth()->user();
        $checkRole = Role::find($authUser->role_id);

        // ✅ frontend friendly params
        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 100)); // guard
        $q = trim((string) $request->input('q', ''));

        $roleId = $request->input('role_id');
        $departmentId = $request->input('department_id');
        $designationId = $request->input('designation_id');

        $query = SystemUser::with([
            "brandOffice" => function ($q2) {
                $q2->with(["brand", "office"]);
            },
            "otherInfo",
            "department",
            "role",
            "designation", // only if relation exists
        ])->orderByDesc('id');

        // ✅ permission gate (same logic as yours)
        if (
            !($checkRole && $checkRole->role_name === "Super Admin") &&
            hasPermission($authUser->role_id, "manage_system_users") !== true
        ) {
            $query->where("id", $authUser->id);
        } else {
            // ✅ filters for admins/managers

            if ($q !== '') {
                $query->where(function ($qq) use ($q) {
                    $qq->where('full_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            }

            if (!empty($roleId)) {
                $query->where('role_id', $roleId);
            }

            if (!empty($departmentId)) {
                $query->where('department_id', $departmentId);
            }

            if (!empty($designationId)) {
                $query->where('designation_id', $designationId);
            }
        }

        $users = $query->paginate($perPage);

        return response()->json([
            "message" => "User list fetched successfully",
            "users" => $users->items(),
            "meta" => [
                "current_page" => $users->currentPage(),
                "last_page" => $users->lastPage(),
                "per_page" => $users->perPage(),
                "total" => $users->total(),
            ],
        ], 200);
    }







    public function menuPermissions(Request $request)
    {
        // Get the authenticated user's role ID
        $roleId = auth()->user()->role_id; // Adjust this based on your auth structure

        // Fetch all permissions for the role with their menu-related data
        $role = Role::where("id", $roleId)
            ->with("rolePermissions.permission")
            ->first();

        if (!$role) {
            return response()->json([
                "code" => 404,
                "message" => "Role not found",
            ], 404);
        }

        // If Super Admin, return all permissions (you might want to adjust this)
        if ($role->role_name === "Super Admin") {
            $allPermissions = Permission::all()->pluck('permission_name');
            return response()->json([
                "code" => 200,
                "permissions" => $allPermissions,
                "is_super_admin" => true
            ]);
        }

        // Filter permissions that are relevant for menu items
        $menuPermissions = [];
        foreach ($role->rolePermissions as $rolePermission) {
            if ($rolePermission->permission) {
                $menuPermissions[] = [
                    'name' => $rolePermission->permission->permission_name,
                    // Add any other menu-related data from permission table if needed
                    'title' => $rolePermission->permission->display_name ?? $rolePermission->permission->permission_name,
                    'icon' => $rolePermission->permission->icon ?? null,
                    'route' => $rolePermission->permission->route ?? null
                ];
            }
        }

        return response()->json([
            "code" => 200,
            "permissions" => $menuPermissions,
            "is_super_admin" => false
        ]);
    }

    public function listCountries(Request $request)
    {
        $countries = Country::orderBy('name')->get();

        return response()->json([
            'data' => $countries
        ], 200);
    }

    public function listbrands(Request $request)
    {
        $brands = Brand::orderBy('name')->get();

        return response()->json([
            'data' => $brands
        ], 200);
    }
    public function listDepertments(Request $request)
    {
        $department = Depertment::orderBy('name')->get();

        return response()->json([
            'data' => $department
        ], 200);
    }

    public function listDesignations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'nullable|uuid|exists:depertments,id', // ⚠️ keep spelling consistent with DB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $query = Designation::query()
            ->orderBy('designation'); // or ->orderBy('name') depending on your column

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $designations = $query->get();

        return response()->json($designations, 200);
    }

    public function listRole(Request $request){
        $roles = Role::orderBy('name')
            ->with(["rolePermissions"=>function ($q) {
                $q->with("permission");
            }])->get();
        return response()->json([
            'data' => $roles
        ], 200);
    }


    public function listOffice(Request $request){
        $office = Office::orderBy('name')->get();
        return response()->json([
            'data' => $office
        ], 200);
    }


//    public function createRole(Request $request)
//    {
//        $site = app('site');
//
//        // ✅ permission gate (adjust permission key if you use a different one)
//        $result = hasPermission(auth()->user()->role_id, 'create_role', $site->id);
//        if ($result !== true) {
//            return $result;
//        }
//
//        $validator = Validator::make($request->all(), [
//            'name' => [
//                'required', 'string', 'max:255',
//                // unique per site among ACTIVE roles
//                Rule::unique('roles', 'name')->where(function ($q) use ($site) {
//                    $q->where('site_id', $site->id)->whereNull('deleted_at');
//                }),
//            ],
//            'group' => ['nullable', 'string', 'max:255'],
//            'description' => ['nullable', 'string', 'max:1000'],
//
//            // optional: if role is tied to department
//            'department_id' => ['nullable', 'uuid', 'exists:depertments,id'], // ⚠️ verify table spelling
//
//            // ✅ permissions to attach
//            'permission_ids' => ['required', 'array', 'min:1'],
//            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
//        ]);
//
//        if ($validator->fails()) {
//            return response()->json([
//                "status"  => 422,
//                "message" => "Validation error",
//                "data"    => $validator->errors()
//            ], 422);
//        }
//
//        $validated = $validator->validated();
//
//        try {
//            $payload = DB::transaction(function () use ($validated, $site) {
//                // ✅ create role
//                $role = new Role();
//                $role->id = (string) Str::uuid();
//                $role->name = $validated['name'];
//                $role->group = $validated['group'] ?? null;
//                $role->description = $validated['description'] ?? null;
//                $role->site_id = $site->id;
//                $role->department_id = $validated['department_id'] ?? null;
//                $role->save();
//
//                // ✅ attach permissions via RolePermissions pivot (soft-delete aware)
//                $permissionIds = array_values(array_unique($validated['permission_ids']));
//
//                // If role_permissions supports soft deletes, we should "restore" existing rows if found.
//                // We'll do this with insert/update semantics: first delete (soft) any old duplicates for safety,
//                // then insert the required rows.
//                // If you prefer restoring instead of deleting, see NOTE below.
//
//                // Remove duplicates for this role/site (soft delete)
//                RolePermission::where('site_id', $site->id)
//                    ->where('role_id', $role->id)
//                    ->delete();
//
//                $rows = [];
//                $now = now();
//
//                foreach ($permissionIds as $pid) {
//                    $rows[] = [
//                        'id' => (string) Str::uuid(),
//                        'role_id' => $role->id,
//                        'permission_id' => $pid,
//                        'site_id' => $site->id,
//                        'deleted_at' => null,
//                        'created_at' => $now,
//                        'updated_at' => $now,
//                    ];
//                }
//
//                if (count($rows)) {
//                    RolePermission::insert($rows);
//                }
//
//                // return fresh role with permissions
//                $fresh = Role::with([
//                    // if you have relationship: permissions()
//                    // 'permissions'
//                ])->where('site_id', $site->id)->where('id', $role->id)->first();
//
//                return [
//                    'role' => $fresh ?? $role,
//                    'permission_ids' => $permissionIds,
//                ];
//            });
//
//            return response()->json([
//                "message" => "Role created successfully.",
//                "role" => $payload['role'],
//                "permission_ids" => $payload['permission_ids'],
//            ], 201);
//
//        } catch (\Throwable $e) {
//            return response()->json([
//                "message" => "Something went wrong while creating the role.",
//                "error" => $e->getMessage()
//            ], 500);
//        }
//    }


    public function createRole(Request $request)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'create_role', $site->id);
        if ($result !== true) {
            return $result;
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where(function ($q) use ($site) {
                    $q->where('site_id', $site->id)->whereNull('deleted_at');
                }),
            ],
            'group' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],

            // optional: if role is tied to department
            'department_id' => ['nullable', 'uuid', 'exists:depertments,id'], // ⚠️ verify table spelling

            // permissions to attach
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status"  => 422,
                "message" => "Validation error",
                "data"    => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $payload = DB::transaction(function () use ($validated, $site) {
                $role = new Role();
                $role->id = (string) Str::uuid();
                $role->name = $validated['name'];
                $role->group = $validated['group'] ?? null;
                $role->description = $validated['description'] ?? null;
                $role->site_id = $site->id;
                $role->department_id = $validated['department_id'] ?? null;
                $role->save();

                $permissionIds = array_values(array_unique($validated['permission_ids']));

                RolePermission::where('site_id', $site->id)
                    ->where('role_id', $role->id)
                    ->delete();

                $rows = [];
                $now = now();

                foreach ($permissionIds as $pid) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'role_id' => $role->id,
                        'permission_id' => $pid,
                        'site_id' => $site->id,
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($rows)) {
                    RolePermission::insert($rows);
                }

                $fresh = Role::where('site_id', $site->id)
                    ->where('id', $role->id)
                    ->first();

                return [
                    'role' => $fresh ?? $role,
                    'permission_ids' => $permissionIds,
                ];
            });

            return response()->json([
                "message" => "Role created successfully.",
                "role" => $payload['role'],
                "permission_ids" => $payload['permission_ids'],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "Something went wrong while creating the role.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * LIST roles (site-scoped) + optional search/filter + pagination
     * Query params:
     * - search (name/group/description)
     * - group
     * - department_id
     * - per_page (default 15)
     */
    public function listRoles(Request $request)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'view_roles', $site->id);
        if ($result !== true) {
            return $result;
        }

        $perPage = (int)($request->get('per_page', 15));
        $search  = trim((string)$request->get('search', ''));
        $group   = $request->get('group');
        $deptId  = $request->get('department_id');

        $q = Role::query()
            ->where('site_id', $site->id)
            ->whereNull('deleted_at')
            ->with(['rolePermissions'=>function ($q) {
                $q->with("permission");
            }]);

        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('group', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($group) {
            $q->where('group', $group);
        }

        if ($deptId) {
            $q->where('department_id', $deptId);
        }

        $roles = $q->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            "message" => "Roles fetched successfully.",
            "data" => $roles
        ], 200);
    }

    /**
     * UPDATE role details + permissions
     */
    public function updateRole(Request $request, string $roleId)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'update_role', $site->id);
        if ($result !== true) {
            return $result;
        }

        $role = Role::where('site_id', $site->id)
            ->where('id', $roleId)
            ->whereNull('deleted_at')
            ->first();

        if (!$role) {
            return response()->json([
                "message" => "Role not found."
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where(function ($q) use ($site, $role) {
                    $q->where('site_id', $site->id)
                        ->whereNull('deleted_at')
                        ->where('id', '!=', $role->id);
                }),
            ],
            'group' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'uuid', 'exists:depertments,id'], // ⚠️ verify table spelling

            // permissions optional on update; if provided, we replace
            'permission_ids' => ['sometimes', 'array', 'min:1'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status"  => 422,
                "message" => "Validation error",
                "data"    => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $payload = DB::transaction(function () use ($validated, $site, $role) {
                if (array_key_exists('name', $validated)) {
                    $role->name = $validated['name'];
                }
                if (array_key_exists('group', $validated)) {
                    $role->group = $validated['group'];
                }
                if (array_key_exists('description', $validated)) {
                    $role->description = $validated['description'];
                }
                if (array_key_exists('department_id', $validated)) {
                    $role->department_id = $validated['department_id'];
                }

                $role->save();

                $permissionIds = null;

                if (array_key_exists('permission_ids', $validated)) {
                    $permissionIds = array_values(array_unique($validated['permission_ids']));

                    // replace role permissions (soft-delete aware)
                    RolePermission::where('site_id', $site->id)
                        ->where('role_id', $role->id)
                        ->delete();

                    $rows = [];
                    $now = now();

                    foreach ($permissionIds as $pid) {
                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'role_id' => $role->id,
                            'permission_id' => $pid,
                            'site_id' => $site->id,
                            'deleted_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (count($rows)) {
                        RolePermission::insert($rows);
                    }
                }

                $fresh = Role::where('site_id', $site->id)
                    ->where('id', $role->id)
                    ->first();

                return [
                    'role' => $fresh ?? $role,
                    'permission_ids' => $permissionIds,
                ];
            });

            return response()->json([
                "message" => "Role updated successfully.",
                "role" => $payload['role'],
                "permission_ids" => $payload['permission_ids'], // null if not provided
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "Something went wrong while updating the role.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE role (soft delete) + soft delete its role_permissions for that site
     */
    public function deleteRole(string $roleId)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'delete_role', $site->id);
        if ($result !== true) {
            return $result;
        }

        $role = Role::where('site_id', $site->id)
            ->where('id', $roleId)
            ->whereNull('deleted_at')
            ->first();

        if (!$role) {
            return response()->json([
                "message" => "Role not found."
            ], 404);
        }

        try {
            DB::transaction(function () use ($site, $role) {
                // soft delete role (if Role uses SoftDeletes)
                $role->delete();

                // soft delete pivot rows too
                RolePermission::where('site_id', $site->id)
                    ->where('role_id', $role->id)
                    ->delete();
            });

            return response()->json([
                "message" => "Role deleted successfully."
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "Something went wrong while deleting the role.",
                "error" => $e->getMessage()
            ], 500);
        }
    }


    public function listPermission(Request $request){
        $site = app('site');

        $permissions = Permission::query()
       // if you store site_id on permissions
            ->whereNull('deleted_at')
            ->orderBy('group')
            ->orderBy('display_name')
            ->get(['id','name','display_name','group']);

        return response()->json([
            'permissions' => $permissions
        ]);

    }





    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('authentication::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('authentication::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
