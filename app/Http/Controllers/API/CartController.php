<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;

class CartController extends Controller
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->middleware(['auth:sanctum']);

        $this->cartService = $cartService;
    }

    public function getCart()
    {
        return new CartResource($this->cartService->getCart());
    }

    public function addToCart(CartRequest $request)
    {
        return $this->cartService->addToCart($request->room_id);
    }

    public function removeFromCart(CartRequest $request)
    {
        return $this->cartService->removeFromCart($request->room_id);
    }

    public function getCartCount()
    {
        return response()->json([
            'count' => $this->cartService->getCartCount(),
        ]);
    }
}
