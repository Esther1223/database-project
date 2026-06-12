<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AffiliationController extends Controller
{
    /**
     * Show the affiliation management page.
     */
    public function index(): Response
    {
        $affiliations = Affiliation::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Affiliation $affiliation): array => [
                'id' => $affiliation->id,
                'name' => $affiliation->name,
                'created_at' => $affiliation->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Affiliations/AffiliationManagePage', [
            'affiliations' => $affiliations,
        ]);
    }

    /**
     * Store a newly created affiliation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:affiliations,name'],
        ]);

        $affiliation = Affiliation::create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => '單位已建立',
            'affiliation' => $affiliation,
        ]);
    }

    /**
     * Update the specified affiliation.
     */
    public function update(Request $request, Affiliation $affiliation): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('affiliations', 'name')->ignore($affiliation->id)],
        ]);

        $affiliation->update([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => '單位已更新',
            'affiliation' => $affiliation->fresh(),
        ]);
    }

    /**
     * Remove the specified affiliation.
     */
    public function destroy(Affiliation $affiliation): JsonResponse
    {
        if ($affiliation->users()->exists() || $affiliation->rooms()->exists()) {
            return response()->json([
                'message' => '此單位仍有使用者或空間，不可刪除。',
            ], 422);
        }

        $affiliation->delete();

        return response()->json([
            'message' => '單位已刪除',
        ]);
    }
}
