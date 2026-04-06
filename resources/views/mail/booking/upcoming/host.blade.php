<x-mail::message>
# Guest Arriving Tomorrow! 📋

Dear {{ $booking->listing->user->full_name }},

This is a friendly reminder that you have a guest arriving **tomorrow** at your listing **"{{ $booking->listing->name }}"**.

## Booking Details:
- **Guest:** {{ $booking->user->full_name }}
- **Guest Email:** {{ $booking->user->email }}
- **Check-in:** {{ $booking->date_start->format('F d, Y') }} ({{ $booking->listing->check_in_time->format('g:i A') }})
- **Check-out:** {{ $booking->date_end->format('F d, Y') }} ({{ $booking->listing->check_out_time->format('g:i A') }})
- **Booking Amount:** ₱{{ number_format($booking->amount, 2) }}

@if(!$booking->listing->is_entire_place)
## Booked Rooms:
@foreach($booking->bookingRooms as $bookingRoom)
- **{{ $bookingRoom->room->roomCategory->name }}**
  - Quantity: {{ $bookingRoom->quantity }}
  - Capacity: {{ $bookingRoom->room->roomCategory->pax }} pax
@endforeach
@endif

@if($booking->message)
## Guest Message:
> {{ $booking->message }}
@endif

## Preparation Checklist:
- ✅ Ensure the property is clean and ready
- ✅ Check that all amenities are in working order
- ✅ Prepare keys or access instructions
- ✅ Be available for the guest's check-in at **{{ $booking->listing->check_in_time->format('g:i A') }}**

---

Thank you for hosting with {{ config('app.name') }}! 🏡

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
