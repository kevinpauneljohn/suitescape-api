<x-mail::message>
# Refund Processed — Booking Dates Updated ✅

Dear {{ $rebookRequest->requester->full_name }},

Your host approved your date-change request for **"{{ $rebookRequest->booking->listing->name }}"**. Since your new dates cost less, a refund of **₱{{ number_format(abs($rebookRequest->difference), 2) }}** has been processed back to your original payment method.

## Updated Booking Details

| | |
|---|---|
| **Check-in** | {{ $rebookRequest->requested_date_start->format('F d, Y') }} |
| **Check-out** | {{ $rebookRequest->requested_date_end->format('F d, Y') }} |
| **Duration** | {{ $rebookRequest->requested_nights }} night{{ $rebookRequest->requested_nights !== 1 ? 's' : '' }} |
| **New Total** | ₱{{ number_format($rebookRequest->new_amount, 2) }} |
| **Refund Amount** | ₱{{ number_format(abs($rebookRequest->difference), 2) }} |

Please allow 3–7 business days for the refund to reflect on your account, depending on your payment provider.

<x-mail::button :url="''">
View Booking in App
</x-mail::button>

If you have any questions, feel free to contact your host or our support team.

Thanks,
{{ config('app.name') }}
</x-mail::message>
