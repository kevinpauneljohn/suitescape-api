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
