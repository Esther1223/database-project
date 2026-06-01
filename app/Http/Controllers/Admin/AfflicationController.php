<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Afflication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AfflicationController extends Controller
{
    /**
     * Show the afflication management page.
     */
    public function index(): Response
    {
        $afflications = Afflication::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Afflication $afflication): array => [
                'id' => $afflication->id,
                'name' => $afflication->name,
                'created_at' => $afflication->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Afflications/AfflicationManagePage', [
            'afflications' => $afflications,
        ]);
    }

    /**
     * Store a newly created afflication.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:afflications,name'],
        ]);

        $afflication = Afflication::create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => '單位已建立',
            'afflication' => $afflication,
        ]);
    }

    /**
     * Update the specified afflication.
     */
    public function update(Request $request, Afflication $afflication): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('afflications', 'name')->ignore($afflication->id)],
        ]);

        $afflication->update([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => '單位已更新',
            'afflication' => $afflication->fresh(),
        ]);
    }

    /**
     * Remove the specified afflication.
     */
    public function destroy(Afflication $afflication): JsonResponse
    {
        if ($afflication->users()->exists() || $afflication->rooms()->exists()) {
            return response()->json([
                'message' => '此單位仍有使用者或空間，不可刪除。',
            ], 422);
        }

        $afflication->delete();

        return response()->json([
            'message' => '單位已刪除',
        ]);
    }
}
