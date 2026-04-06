<x-mail::message>
# Booking Confirmation

Dear {{ $booking->user->full_name }},

Your booking for "{{ $booking->listing->name }}" has been confirmed!

## Booking Details:
- Host: {{ $booking->listing->user->full_name }}
- Check-in: {{ $booking->date_start->format('F d, Y') }} ({{ $booking->listing->check_in_time->format('g:i A') }})
- Check-out: {{ $booking->date_end->format('F d, Y') }} ({{ $booking->listing->check_out_time->format('g:i A') }})

## Price Breakdown:
@php
    // subtotal = pure accommodation + addons (before any fees)
    $subtotal = $booking->amount - ($booking->guest_service_fee ?? 0) - ($booking->vat ?? 0);
    // Bake service fee into accommodation — guests see one combined accommodation figure
    $accommodationWithFee = $subtotal + ($booking->guest_service_fee ?? 0);
@endphp
- Accommodation: ₱{{ number_format($accommodationWithFee, 2) }}
@if($booking->vat)
- VAT (12%): ₱{{ number_format($booking->vat, 2) }}
@endif
- **Total charged: ₱{{ number_format($booking->amount, 2) }}**

@if(!$booking->listing->is_entire_place)
## Booked Rooms:
@foreach($booking->bookingRooms as $bookingRoom)
- {{ $bookingRoom->room->roomCategory->name }}
- Quantity: {{ $bookingRoom->quantity }}
- Capacity: {{ $bookingRoom->room->roomCategory->pax }} pax
- Bed types:
@foreach($bookingRoom->room->roomCategory->type_of_beds as $bedType => $count)
    - {{ ucfirst($bedType) }}: {{ $count }}
@endforeach
- Floor area: {{ $bookingRoom->room->roomCategory->floor_area }} sqm
@endforeach
@endif

## Property Details:
- Type: {{ ucfirst($booking->listing->facility_type) }}
- Location: {{ $booking->listing->location }}
@if($booking->listing->parking_lot)
- Parking available
@endif
@if($booking->listing->is_pet_allowed)
- Pets allowed
@endif

---

We hope you enjoy your stay!

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
