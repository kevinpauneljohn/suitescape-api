<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Constant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ListingFeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get fee settings for a listing
     */
    public function getFeeSettings(string $listingId): JsonResponse
    {
        $listing = Listing::findOrFail($listingId);

        // Get global default fee
        $globalFee = 0;
        $feeConstant = Constant::where('key', 'suitescape_fee')->first();
        if ($feeConstant) {
            $globalFee = (float) $feeConstant->value;
        }

        return response()->json([
            'listing_id' => $listing->id,
            'listing_name' => $listing->name,
            'custom_suitescape_fee' => $listing->custom_suitescape_fee,
            'is_partner' => $listing->is_partner,
            'partner_notes' => $listing->partner_notes,
            'effective_fee' => $listing->custom_suitescape_fee ?? $globalFee,
            'global_default_fee' => $globalFee,
            'using_custom_fee' => $listing->custom_suitescape_fee !== null,
        ]);
    }

    /**
     * Update fee settings for a listing (Admin only)
     */
    public function updateFeeSettings(Request $request, string $listingId): JsonResponse
    {
        // Check if user is admin
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $listing = Listing::findOrFail($listingId);

        $validated = $request->validate([
            'custom_suitescape_fee' => 'nullable|numeric|min:0',
            'is_partner' => 'boolean',
            'partner_notes' => 'nullable|string|max:255',
        ]);

        $listing->update($validated);

        // Get global default fee for response
        $globalFee = 0;
        $feeConstant = Constant::where('key', 'suitescape_fee')->first();
        if ($feeConstant) {
            $globalFee = (float) $feeConstant->value;
        }

        return response()->json([
            'message' => 'Fee settings updated successfully',
            'listing_id' => $listing->id,
            'listing_name' => $listing->name,
            'custom_suitescape_fee' => $listing->custom_suitescape_fee,
            'is_partner' => $listing->is_partner,
            'partner_notes' => $listing->partner_notes,
            'effective_fee' => $listing->custom_suitescape_fee ?? $globalFee,
            'global_default_fee' => $globalFee,
        ]);
    }

    /**
     * Remove custom fee (revert to global default)
     */
    public function removeCustomFee(Request $request, string $listingId): JsonResponse
    {
        // Check if user is admin
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $listing = Listing::findOrFail($listingId);

        $listing->update([
            'custom_suitescape_fee' => null,
            'is_partner' => false,
            'partner_notes' => null,
        ]);

        return response()->json([
            'message' => 'Custom fee removed. Listing will now use global default fee.',
            'listing_id' => $listing->id,
            'listing_name' => $listing->name,
        ]);
    }

    /**
     * Get all partner listings with custom fees
     */
    public function getPartnerListings(Request $request): JsonResponse
    {
        // Check if user is admin
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $partners = Listing::where('is_partner', true)
            ->orWhereNotNull('custom_suitescape_fee')
            ->with('user:id,firstname,lastname,email')
            ->get(['id', 'name', 'user_id', 'custom_suitescape_fee', 'is_partner', 'partner_notes']);

        // Get global default fee
        $globalFee = 0;
        $feeConstant = Constant::where('key', 'suitescape_fee')->first();
        if ($feeConstant) {
            $globalFee = (float) $feeConstant->value;
        }

        return response()->json([
            'global_default_fee' => $globalFee,
            'partner_count' => $partners->count(),
            'partners' => $partners->map(function ($listing) use ($globalFee) {
                return [
                    'listing_id' => $listing->id,
                    'listing_name' => $listing->name,
                    'host_name' => $listing->user ? $listing->user->firstname . ' ' . $listing->user->lastname : 'Unknown',
                    'host_email' => $listing->user->email ?? null,
                    'custom_suitescape_fee' => $listing->custom_suitescape_fee,
                    'effective_fee' => $listing->custom_suitescape_fee ?? $globalFee,
                    'is_partner' => $listing->is_partner,
                    'partner_notes' => $listing->partner_notes,
                ];
            }),
        ]);
    }
}
