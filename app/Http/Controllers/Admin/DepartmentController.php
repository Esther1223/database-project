<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Show the department management page.
     */
    public function index(): Response
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'created_at' => $department->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Departments/DepartmentManagePage', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
        ]);

        $department = Department::create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => '單位已建立',
            'department' => $department,
        ]);
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
        ]);

        $department->update([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => '單位已更新',
            'department' => $department->fresh(),
        ]);
    }

    /**
     * Remove the specified department.
     */
    public function destroy(Department $department): JsonResponse
    {
        $department->delete();

        return response()->json([
            'message' => '單位已刪除',
        ]);
    }
}
