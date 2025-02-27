<x-mail::message>
# Booking Cancellation

Dear {{ $booking->user->full_name }},

Your booking for "{{ $booking->listing->name }}" has been cancelled.

## Booking Details:
- Host: {{ $booking->listing->user->full_name }}
- Check-in: {{ $booking->date_start->format('F d, Y') }} ({{ $booking->listing->check_in_time->format('g:i A') }})
- Check-out: {{ $booking->date_end->format('F d, Y') }} ({{ $booking->listing->check_out_time->format('g:i A') }})
@if(isset($cancellationFee))
- Cancellation fee: ₱{{ number_format($cancellationFee, 2) }}
@endif
@if(isset($suitescapeCancellationFee))
- Platform fee: ₱{{ number_format($suitescapeCancellationFee, 2) }}
@endif
- Amount: ₱{{ number_format($booking->amount, 2) }}

@if(!$booking->listing->is_entire_place)
## Cancelled Rooms:
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

@if($booking->cancellation_reason)
## Cancellation Reason:
{{ $booking->cancellation_reason }}
@endif

@if($cancellationPolicy)
## Cancellation Policy:
{{ $cancellationPolicy}}
@endif

---

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
