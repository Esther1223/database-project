<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->orderBy('role_type')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'role_type' => $role->role_type,
                'users_count' => $role->users_count,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Roles/RoleManagePage', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_type' => ['required', 'string', 'max:50', 'unique:roles,role_type'],
        ]);

        $role = Role::create($validated);

        return response()->json([
            'message' => '角色已建立',
            'role' => $role,
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'role_type' => ['required', 'string', 'max:50', Rule::unique('roles', 'role_type')->ignore($role->id)],
        ]);

        $role->update($validated);

        return response()->json([
            'message' => '角色已更新',
            'role' => $role->fresh(),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json([
                'message' => '此角色仍有使用者，不可刪除。',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => '角色已刪除',
        ]);
    }
}
