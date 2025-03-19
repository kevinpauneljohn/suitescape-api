<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayoutMethodRequest;
use App\Http\Resources\PayoutMethodResource;
use App\Services\PayoutService;
use Exception;

class PayoutController extends Controller
{
    private PayoutService $payoutService;

    public function __construct(PayoutService $payoutService)
    {
        $this->middleware('auth:sanctum');

        $this->payoutService = $payoutService;
    }

    /**
     * Get Payout Methods
     *
     * Retrieves a collection of available payout methods.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getPayoutMethods()
    {
        $payoutMethods = $this->payoutService->getPayoutMethods();

        return PayoutMethodResource::collection($payoutMethods);
    }

    /**
     * Add Payout Method
     *
     * Adds a new payout method based on the provided details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPayoutMethod(PayoutMethodRequest $request)
    {
        $payoutMethod = $this->payoutService->addPayoutMethod($request->validated());

        return response()->json([
            'message' => 'Payout method added',
            'payout_method' => new PayoutMethodResource($payoutMethod->load('payoutable')),
        ]);
    }

    /**
     * Update Payout Method
     *
     * Updates an existing payout method based on the provided details.
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function updatePayoutMethod(PayoutMethodRequest $request, string $id)
    {
        $payoutMethod = $this->payoutService->updatePayoutMethod($id, $request->validated());

        return response()->json([
            'message' => 'Payout method updated',
            'payout_method' => new PayoutMethodResource($payoutMethod->load('payoutable')),
        ]);
    }

    /**
     * Delete Payout Method
     *
     * Deletes an existing payout method based on the provided ID.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePayoutMethod(string $id)
    {
        $this->payoutService->deletePayoutMethod($id);

        return response()->json([
            'message' => 'Payout method deleted',
        ]);
    }
}
