<?php

namespace App\Services;

class CartService
{
    public function getCart()
    {
        $user = auth()->user();

        return $user->cart()->with(['items.room.roomCategory', 'items.room.listing.host', 'items.room.listing.images'])->first();
    }

    public function getCartCount()
    {
        return auth()->user()->cart->items()->count();
    }

    public function addToCart(string $roomId)
    {
        $cart = auth()->user()->cart;

        // Check if room is already in cart
        if ($cart->items()->where('room_id', $roomId)->exists()) {
            return response()->json([
                'message' => 'Room already in cart!',
            ], 422);
        }

        $cart->items()->create([
            'room_id' => $roomId,
        ]);

        return response()->json([
            'message' => 'Room added to cart successfully!',
        ]);
    }

    public function removeFromCart(string $roomId)
    {
        $cart = auth()->user()->cart;

        $cart->items()->where('room_id', $roomId)->delete();

        return response()->json([
            'message' => 'Room removed from cart successfully!',
        ]);
    }
}
