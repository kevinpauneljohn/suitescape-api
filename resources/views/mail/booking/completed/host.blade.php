<x-mail::message>
# New Booking Confirmed

Dear {{ $booking->listing->user->full_name }},

You have a new confirmed booking for your listing "{{ $booking->listing->name }}".

## Booking Details:
- Guest: {{ $booking->user->full_name }}
- Check-in: {{ $booking->date_start->format('F d, Y') }}
- Check-out: {{ $booking->date_end->format('F d, Y') }}

## Payment Summary:
@php
    // Recompute everything from first principles so this email is always
    // correct regardless of when it is sent (before or after completeBookingWithFee runs).

    $guestServiceFee = (float) ($booking->guest_service_fee ?? 0);
    $vat             = (float) ($booking->vat ?? 0);

    // Base accommodation subtotal (no guest-facing fees)
    $subtotal  = (float) $booking->amount - $guestServiceFee - $vat;
    $hostGross = $subtotal;  // host earns on the base accommodation, not on fees

    // Platform fee — use stored value if already set by completeBookingWithFee,
    // otherwise calculate from the listing's custom rate or the global constant.
    $storedFee = (float) ($booking->suitescape_fee ?? 0);
    if ($storedFee > 0) {
        $suitescapeFee  = $storedFee;
        $hostNetEarnings = (float) ($booking->host_earnings ?? ($hostGross - $suitescapeFee));
        $feeRate = $hostGross > 0 ? round($suitescapeFee / $hostGross * 100, 2) : 3;
    } else {
        // Booking not yet completed — calculate the expected fee
        $feeRate = 3; // default 3%
        if ($booking->listing && $booking->listing->custom_suitescape_fee !== null) {
            $feeRate = (float) $booking->listing->custom_suitescape_fee;
        } else {
            $feeConstant = \App\Models\Constant::where('key', 'suitescape_fee')->first();
            if ($feeConstant) {
                $feeRate = (float) $feeConstant->value;
            }
        }
        $suitescapeFee   = round($hostGross * ($feeRate / 100), 2);
        $hostNetEarnings = round($hostGross - $suitescapeFee, 2);
    }
@endphp
- Accommodation subtotal: ₱{{ number_format($subtotal, 2) }}
@if($guestServiceFee > 0)
- Suitescape service fee (15%): ₱{{ number_format($guestServiceFee, 2) }}
@endif
@if($vat > 0)
- VAT (12%): ₱{{ number_format($vat, 2) }}
@endif
- **Total charged to guest: ₱{{ number_format($booking->amount, 2) }}**

## Your Earnings:
- Your gross (accommodation subtotal): ₱{{ number_format($hostGross, 2) }}
- Suitescape platform fee ({{ $feeRate }}%): −₱{{ number_format($suitescapeFee, 2) }}
- **Your net earnings: ₱{{ number_format($hostNetEarnings, 2) }}**

@if(!$booking->listing->is_entire_place)
## Booked Rooms:
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

---

Thank you for hosting with {{ config('app.name') }}!

Best regards,<br>
{{ config('app.name') }} Team
</x-mail::message>
