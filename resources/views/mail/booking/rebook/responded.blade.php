<x-mail::message>
@if($rebookRequest->status === 'approved')
# Your Date-Change Request Was Approved ✅

Dear {{ $rebookRequest->requester->full_name }},

Great news! Your host has **approved** your request to change the booking dates for **"{{ $rebookRequest->booking->listing->name }}"**.

## Updated Booking Details:
- **New Check-in:** {{ $rebookRequest->requested_date_start->format('F d, Y') }}
- **New Check-out:** {{ $rebookRequest->requested_date_end->format('F d, Y') }}
- **Duration:** {{ $rebookRequest->requested_nights }} night{{ $rebookRequest->requested_nights !== 1 ? 's' : '' }}
- **New Total:** ₱{{ number_format($rebookRequest->new_amount, 2) }}

@if($rebookRequest->difference > 0)
## Additional Payment Required
An additional **₱{{ number_format($rebookRequest->difference, 2) }}** is due for the extra nights. Please open the Suitescape app to complete your payment and confirm the new dates.

<x-mail::button :url="''">
Open Suitescape App
</x-mail::button>
@elseif($rebookRequest->difference < 0)
## Refund Initiated ✅
A refund of **₱{{ number_format(abs($rebookRequest->difference), 2) }}** has been initiated and is now being processed back to your original payment method. You should receive it within **3–7 business days**, depending on your bank or e-wallet provider.

Your booking dates have been updated automatically.
@else
Your booking dates have been updated. No price change was needed.
@endif

@else
# Your Date-Change Request Was Rejected ❌

Dear {{ $rebookRequest->requester->full_name }},

Unfortunately, your host has **rejected** your request to change the booking dates for **"{{ $rebookRequest->booking->listing->name }}"**.

## Requested Dates (Not Approved):
- **Requested Check-in:** {{ $rebookRequest->requested_date_start->format('F d, Y') }}
- **Requested Check-out:** {{ $rebookRequest->requested_date_end->format('F d, Y') }}

## Your Original Booking Remains Unchanged:
- **Check-in:** {{ $rebookRequest->booking->date_start->format('F d, Y') }}
- **Check-out:** {{ $rebookRequest->booking->date_end->format('F d, Y') }}
- **Amount:** ₱{{ number_format($rebookRequest->original_amount, 2) }}

@if($rebookRequest->host_note)
## Host's Note:
> {{ $rebookRequest->host_note }}
@endif

If you have questions, please contact your host through the Suitescape app.
@endif

---

Thank you for using {{ config('app.name') }}! 🏡

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
