<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\YearlyEarningsRequest;
use App\Services\EarningsRetrievalService;
use Exception;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    private EarningsRetrievalService $earningsRetrievalService;

    public function __construct(EarningsRetrievalService $earningsRetrievalService)
    {
        $this->earningsRetrievalService = $earningsRetrievalService;
    }

    /**
     * Get Yearly Earnings
     *
     * Retrieves the yearly earnings for a specific host and optionally for a specific listing.
     * Requires a host ID to be provided. If a listing ID is provided, earnings are filtered by the listing.
     *
     * @param YearlyEarningsRequest $request
     * @param int $year
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function getYearlyEarnings(YearlyEarningsRequest $request, int $year)
    {
        $validated = $request->validated();

        $hostId = $validated['host_id'] ?? auth('sanctum')->id();
        $listingId = $validated['listing_id'] ?? null;

        if (! $hostId) {
            throw new Exception('No host provided.');
        }

        return response()->json([
            'data' => $this->earningsRetrievalService->getYearlyEarnings($year, $hostId, $listingId),
        ]);
    }

    /**
     * Get Available Years
     *
     * Retrieves the years for which earnings data is available for a specific host.
     * Requires a host ID to be provided.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function getAvailableYears(Request $request)
    {
        $hostId = $request->host_id ?? auth('sanctum')->id();

        if (! $hostId) {
            throw new Exception('No host provided.');
        }

        return response()->json([
            'data' => $this->earningsRetrievalService->getAvailableYears($hostId),
        ]);
    }
}
