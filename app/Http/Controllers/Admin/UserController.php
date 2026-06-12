<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show the user management page.
     */
    public function index(): Response
    {
        $users = User::query()
            ->with(['roles', 'affiliation'])
            ->orderBy('name')
            ->get()
            ->map(function (User $user): array {
                $affiliation = $user->getRelation('affiliation');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'affiliation' => $affiliation?->name,
                    'affiliation_id' => $user->affiliation_id,
                    'affiliation_name' => $affiliation?->name,
                    'is_active' => (bool) ($user->is_active ?? true),
                    'roles' => $user->roles->map(fn (Role $role): array => [
                        'id' => $role->id,
                        'role_type' => $role->role_type,
                    ]),
                ];
            });

        $roles = Role::query()
            ->orderBy('role_type')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'role_type' => $role->role_type,
            ]);

        $affiliations = Affiliation::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Affiliation $affiliation): array => [
                'id' => $affiliation->id,
                'name' => $affiliation->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Users/UserListPage', [
            'users' => $users,
            'roles' => $roles,
            'affiliations' => $affiliations,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:User,email'],
            'affiliation_id' => ['required', 'integer', 'exists:Affiliation,id'],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:Role,id'],
        ], [
            'role_ids.required' => '請至少選擇一個角色。',
            'role_ids.min' => '請至少選擇一個角色。',
        ]);

        $roleIds = array_map('intval', $validated['role_ids'] ?? []);

        $adminRoleId = Role::query()->where('role_type', '管理員')->value('id');

        if ($adminRoleId !== null && in_array($adminRoleId, $roleIds, true)) {
            $adminCount = User::whereHas('roles', fn ($query) => $query->where('role_type', '管理員'))->count();

            if ($adminCount >= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => '系統只能有一位管理員。',
                ]);
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
            'affiliation_id' => $validated['affiliation_id'],
        ]);

        $user->roles()->sync($roleIds);

        return response()->json([
            'message' => '使用者已建立',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('User', 'email')->ignore($user->id)],
            'affiliation_id' => ['required', 'integer', 'exists:Affiliation,id'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:Role,id'],
        ], [
            'role_ids.required' => '請至少選擇一個角色。',
            'role_ids.min' => '請至少選擇一個角色。',
        ]);

        $roleIds = array_map('intval', $validated['role_ids'] ?? $user->roles()->pluck('Role.id')->all());

        $adminRoleId = Role::query()->where('role_type', '管理員')->value('id');

        if (
            $adminRoleId !== null
            && in_array($adminRoleId, $roleIds, true)
            && ! $user->roles()->where('role_type', '管理員')->exists()
        ) {
            $adminCount = User::whereHas('roles', fn ($query) => $query->where('role_type', '管理員'))
                ->whereKeyNot($user->id)
                ->count();

            if ($adminCount >= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => '系統只能有一位管理員。',
                ]);
            }
        }

        if (
            array_key_exists('is_active', $validated)
            && $user->id === $request->user()->id
            && $validated['is_active'] === false
        ) {
            throw ValidationException::withMessages([
                'is_active' => '不能停用目前登入中的帳號。',
            ]);
        }

        if (
            $user->roles()->where('role_type', '管理員')->exists()
            && !in_array((int) Role::query()->where('role_type', '管理員')->value('id'), $roleIds, true)
        ) {
            $adminCount = User::whereHas('roles', fn ($query) => $query->where('role_type', '管理員'))->count();

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => '至少需要保留一位管理員。',
                ]);
            }
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'affiliation_id' => $validated['affiliation_id'],
        ];

        if (!empty($validated['password'] ?? null)) {
            $payload['password'] = Hash::make($validated['password']);
        }

        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = $validated['is_active'];
        }

        $user->forceFill($payload)->save();
        $user->roles()->sync($roleIds);

        return response()->json([
            'message' => '使用者已更新',
            'user' => $user->fresh()->load('roles'),
        ]);
    }

    /**
     * Delete the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'user_id' => '不能刪除目前登入中的帳號。',
            ]);
        }
        // Disallow deleting admin users from backend
        if ($user->roles()->where('role_type', '管理員')->exists()) {
            throw ValidationException::withMessages([
                'user_id' => '無法刪除管理員帳號。',
            ]);
        }

        $user->delete();

        return response()->json([
            'message' => '使用者已刪除',
        ]);
    }

    /**
     * Toggle a user's active state.
     */
    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        // Prevent deactivating admin users from backend
        if ($validated['is_active'] === false && $user->roles()->where('role_type', '管理員')->exists()) {
            throw ValidationException::withMessages([
                'is_active' => '無法停用管理員帳號。',
            ]);
        }

        // Prevent deactivating currently authenticated user
        if ($validated['is_active'] === false && $request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'is_active' => '不能停用目前登入中的帳號。',
            ]);
        }

        $user->forceFill([
            'is_active' => $validated['is_active'],
        ])->save();

        return response()->json([
            'message' => '使用者狀態已更新',
        ]);
    }

    /**
     * Replace the user's role assignments.
     */
    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:Role,id'],
        ], [
            'role_ids.required' => '請至少選擇一個角色。',
            'role_ids.min' => '請至少選擇一個角色。',
        ]);

        $adminRoleId = Role::query()->where('role_type', '管理員')->value('id');
        $requestedRoleIds = $validated['role_ids'] ?? [];

        if (
            $adminRoleId !== null
            && in_array($adminRoleId, $requestedRoleIds, true)
            && ! $user->roles()->where('role_type', '管理員')->exists()
        ) {
            $adminCount = User::whereHas('roles', fn ($query) => $query->where('role_type', '管理員'))
                ->whereKeyNot($user->id)
                ->count();

            if ($adminCount >= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => '系統只能有一位管理員。',
                ]);
            }
        }

        if (
            $adminRoleId !== null
            && $user->roles()->where('role_type', '管理員')->exists()
            && !in_array($adminRoleId, $requestedRoleIds, true)
        ) {
            $adminCount = User::whereHas('roles', fn ($query) => $query->where('role_type', '管理員'))->count();

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => '至少需要保留一位管理員。',
                ]);
            }
        }

        $user->roles()->sync($requestedRoleIds);

        return response()->json([
            'message' => '使用者角色已更新',
        ]);
    }
}
