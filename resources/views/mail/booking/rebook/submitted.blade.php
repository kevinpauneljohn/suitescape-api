<x-mail::message>
# Date-Change Request Received 📅

Dear {{ $rebookRequest->booking->listing->user->full_name }},

**{{ $rebookRequest->requester->full_name }}** has requested to change the dates for their booking at **"{{ $rebookRequest->booking->listing->name }}"**.

## Request Details:
- **Guest:** {{ $rebookRequest->requester->full_name }} ({{ $rebookRequest->requester->email }})
- **Current Dates:** {{ $rebookRequest->booking->date_start->format('F d, Y') }} → {{ $rebookRequest->booking->date_end->format('F d, Y') }} ({{ $rebookRequest->booking->date_start->diffInDays($rebookRequest->booking->date_end) }} night{{ $rebookRequest->booking->date_start->diffInDays($rebookRequest->booking->date_end) !== 1 ? 's' : '' }})
- **Requested Dates:** {{ $rebookRequest->requested_date_start->format('F d, Y') }} → {{ $rebookRequest->requested_date_end->format('F d, Y') }} ({{ $rebookRequest->requested_nights }} night{{ $rebookRequest->requested_nights !== 1 ? 's' : '' }})

## Price Impact:
- **Original Amount:** ₱{{ number_format($rebookRequest->original_amount, 2) }}
- **New Amount:** ₱{{ number_format($rebookRequest->new_amount, 2) }}
@if($rebookRequest->difference > 0)
- **Guest will pay:** +₱{{ number_format($rebookRequest->difference, 2) }} additional
@elseif($rebookRequest->difference < 0)
- **Guest refund:** ₱{{ number_format(abs($rebookRequest->difference), 2) }} will be refunded
@else
- **No price change**
@endif

@if($rebookRequest->reason)
## Guest's Reason:
> {{ $rebookRequest->reason }}
@endif

---

Please open the **Suitescape app** to approve or reject this request. The guest is waiting for your response.

Thank you for hosting with {{ config('app.name') }}! 🏡

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
