<x-mail::message>
# New Booking Confirmed

Dear {{ $booking->listing->user->full_name }},

You have a new confirmed booking for your listing "{{ $booking->listing->name }}".

## Booking Details:
- Guest: {{ $booking->user->full_name }}
- Check-in: {{ $booking->date_start->format('F d, Y') }}
- Check-out: {{ $booking->date_end->format('F d, Y') }}
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

---

<br>If you have any questions or need to contact the guest, you can reach them at {{ $booking->user->email }}.

Thank you for hosting with {{ config('app.name') }}!

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
