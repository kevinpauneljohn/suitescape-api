<x-mail::message>
# Your Stay is Tomorrow! 🎉

Dear {{ $booking->user->full_name }},

This is a friendly reminder that your booking at **"{{ $booking->listing->name }}"** begins **tomorrow**!

## Booking Details:
- **Host:** {{ $booking->listing->user->full_name }}
- **Check-in:** {{ $booking->date_start->format('F d, Y') }} ({{ $booking->listing->check_in_time->format('g:i A') }})
- **Check-out:** {{ $booking->date_end->format('F d, Y') }} ({{ $booking->listing->check_out_time->format('g:i A') }})
- **Total Paid:** ₱{{ number_format($booking->amount, 2) }}

@if(!$booking->listing->is_entire_place)
## Your Booked Rooms:
@foreach($booking->bookingRooms as $bookingRoom)
- **{{ $bookingRoom->room->roomCategory->name }}**
  - Quantity: {{ $bookingRoom->quantity }}
  - Capacity: {{ $bookingRoom->room->roomCategory->pax }} pax
@endforeach
@endif

## Property Info:
- **Type:** {{ ucfirst($booking->listing->facility_type) }}
- **Location:** {{ $booking->listing->location }}
@if($booking->listing->parking_lot)
- ✅ Parking available
@endif
@if($booking->listing->is_pet_allowed)
- 🐾 Pets allowed
@endif

## Quick Reminders:
- Please check in on time at **{{ $booking->listing->check_in_time->format('g:i A') }}**
- Have a valid ID ready for verification
- Contact your host if you have any questions

---

We hope you have a wonderful stay! 🏡

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
