<x-mail::message>
# Booking Confirmation

Dear {{ $booking->user->full_name }},

Your booking for "{{ $booking->listing->name }}" has been confirmed!

## Booking Details:
- Host: {{ $booking->listing->user->full_name }}
- Check-in: {{ $booking->date_start->format('F d, Y') }} ({{ $booking->listing->check_in_time->format('g:i A') }})
- Check-out: {{ $booking->date_end->format('F d, Y') }} ({{ $booking->listing->check_out_time->format('g:i A') }})
- Total price: ₱{{ number_format($booking->amount, 2) }}

@if(!$booking->listing->is_entire_place)
    ## Booked Rooms:
    @foreach($booking->bookingRooms as $bookingRoom)
        - {{ $bookingRoom->room->roomCategory->name }}
            - Quantity: {{ $bookingRoom->quantity }}
            - Capacity: {{ $bookingRoom->room->roomCategory->pax }} pax
            - Bed types:
            @foreach($bookingRoom->room->roomCategory->type_of_beds as $bedType => $count)
                • {{ ucfirst($bedType) }}: {{ $count }}
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

<br>If you need to contact the host, you can reach them at {{ $booking->listing->user->email }}.

We hope you enjoy your stay!

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
