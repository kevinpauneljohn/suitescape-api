<x-mail::message>
# Booking Dates Updated! 🎉

Dear {{ $rebookRequest->requester->full_name }},

Your additional payment has been received and your booking for **"{{ $rebookRequest->booking->listing->name }}"** has been successfully updated to the new dates.

## Updated Booking Details

| | |
|---|---|
| **Check-in** | {{ $rebookRequest->requested_date_start->format('F d, Y') }} |
| **Check-out** | {{ $rebookRequest->requested_date_end->format('F d, Y') }} |
| **Duration** | {{ $rebookRequest->requested_nights }} night{{ $rebookRequest->requested_nights !== 1 ? 's' : '' }} |
| **Total Amount** | ₱{{ number_format($rebookRequest->new_amount, 2) }} |
| **Additional Paid** | ₱{{ number_format($rebookRequest->difference, 2) }} |

Everything is confirmed — enjoy your upcoming stay!

<x-mail::button :url="''">
View Booking in App
</x-mail::button>

If you have any questions, feel free to contact your host or our support team.

Thanks,
{{ config('app.name') }}
</x-mail::message>
