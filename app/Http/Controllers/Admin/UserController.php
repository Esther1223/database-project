<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
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
            ->with(['roles', 'department'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'affiliation' => $user->affiliation,
                'department_id' => $user->department_id,
                'department_name' => $user->department?->name,
                'is_active' => (bool) ($user->is_active ?? true),
                'roles' => $user->roles->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'role_type' => $role->role_type,
                ]),
            ]);

        $roles = Role::query()
            ->orderBy('role_type')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'role_type' => $role->role_type,
            ]);

        $departments = Department::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Users/UserListPage', [
            'users' => $users,
            'roles' => $roles,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $roleIds = array_map('intval', $validated['role_ids'] ?? []);

        $department = Department::find($validated['department_id']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'affiliation' => $department?->name ?? '未指定',
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
            'department_id' => $validated['department_id'],
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
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $roleIds = array_map('intval', $validated['role_ids'] ?? $user->roles()->pluck('roles.id')->all());

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

        $department = Department::find($validated['department_id']);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'affiliation' => $department?->name ?? '未指定',
            'department_id' => $validated['department_id'],
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

        if ($user->roles()->where('role_type', '管理員')->exists()) {
            $adminCount = User::whereHas('roles', fn ($query) => $query->where('role_type', '管理員'))->count();

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'user_id' => '至少需要保留一位管理員。',
                ]);
            }
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
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $adminRoleId = Role::query()->where('role_type', '管理員')->value('id');
        $requestedRoleIds = $validated['role_ids'] ?? [];

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