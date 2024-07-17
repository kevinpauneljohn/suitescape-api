<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConstantUpdateRequest;
use App\Http\Resources\ConstantResource;
use App\Services\ConstantService;
use Spatie\Permission\Exceptions\UnauthorizedException;

class ConstantController extends Controller
{
    private ConstantService $constantService;

    public function __construct(ConstantService $constantService)
    {
        $this->middleware('auth:sanctum')->only('updateConstant');

        $this->constantService = $constantService;
    }

    /**
     * Get All Constants
     *
     * Retrieves a collection of all constants.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllConstants()
    {
        return ConstantResource::collection($this->constantService->getAllConstants());
    }

    /**
     * Get Constant
     *
     * Retrieves a specific constant by key.
     *
     * @param string $key
     * @return ConstantResource
     */
    public function getConstant(string $key)
    {
        return new ConstantResource($this->constantService->getConstant($key));
    }

    /**
     * Update Constant
     *
     * Updates the value of a specific constant.
     *
     * @param string $key
     * @param ConstantUpdateRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws UnauthorizedException
     */
    public function updateConstant(string $key, ConstantUpdateRequest $request)
    {
        $validated = $request->validated();

        if (auth()->user()->cannot('update constant')) {
            throw new UnauthorizedException(403);
        }

        $this->constantService->updateConstant($key, $validated['value']);

        return response()->json([
            'message' => 'Constant updated successfully.',
        ]);
    }
}
