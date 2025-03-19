<x-mail::message>
# Booking Cancellation Notice

Dear {{ $booking->listing->user->full_name }},

A booking for "{{ $booking->listing->name }}" has been cancelled.

## Guest Information:
- Name: {{ $booking->user->full_name }}
- Email: {{ $booking->user->email }}

## Booking Details:
- Check-in: {{ $booking->date_start->format('F d, Y') }} ({{ $booking->listing->check_in_time->format('g:i A') }})
- Check-out: {{ $booking->date_end->format('F d, Y') }} ({{ $booking->listing->check_out_time->format('g:i A') }})
@if(isset($cancellationFee))
- Cancellation fee (to be charged): ₱{{ number_format($cancellationFee, 2) }}
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

@if($booking->cancellation_reason)
## Cancellation Reason:
{{ $booking->cancellation_reason }}
@endif

@if($cancellationPolicy)
## Applied Cancellation Policy:
{{ $cancellationPolicy }}
@endif

---

<br>The dates for this booking are now available again in your calendar. You may want to review your availability settings if needed.

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
