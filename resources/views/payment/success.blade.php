<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Suitescape PH</title>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    @vite('resources/css/status-pages.css')
</head>
<body>
<div class="container animate-fade-in">
    <div class="logo-container">
        <img
            src="{{ asset('suitescape-logo.png') }}"
            alt="Suitescape PH"
            class="logo"
            onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'40\' viewBox=\'0 0 200 40\'%3E%3Crect width=\'200\' height=\'40\' fill=\'%23f0f0f0\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' font-family=\'Arial\' font-size=\'12\' fill=\'%23666\' text-anchor=\'middle\' dy=\'.3em\'%3ESuitescape PH%3C/text%3E%3C/svg%3E'"
        >
    </div>

    <div class="card">
        <div class="icon-container success">
            <svg class="icon success" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="title">Payment Successful!</h1>

        <p class="description">
            Your payment has been processed successfully. Thank you for booking with Suitescape PH!
        </p>

        <div class="payment-details">
            <div class="payment-detail-row">
                <span class="payment-detail-label">Reference Number</span>
                <span class="payment-detail-value">{{ $invoice->reference_number }}</span>
            </div>
            <div class="payment-detail-row">
                <span class="payment-detail-label">Booking Dates</span>
                <span class="payment-detail-value">
                    {{ $booking->date_start->format('M d, Y') }} - {{ $booking->date_end->format('M d, Y') }}
                </span>
            </div>
            <div class="payment-detail-row">
                <span class="payment-detail-label">Property</span>
                <span class="payment-detail-value">{{ $booking->listing->name }}</span>
            </div>
            @if($invoice->coupon_id)
                <div class="payment-detail-row">
                    <span class="payment-detail-label">Discount</span>
                    <span class="payment-detail-value">-₱{{ number_format($invoice->coupon_discount_amount, 2) }}</span>
                </div>
            @endif
            <div class="payment-detail-row">
                <span class="payment-detail-label payment-total">Total Amount</span>
                <span class="payment-detail-value payment-total">₱{{ number_format($booking->amount, 2) }}</span>
            </div>
        </div>

        <p class="support-text">
            Need assistance? Contact us at<br>
            <a href="mailto:suitescape.ph.2024@gmail.com" class="support-email">
                suitescape.ph.2024@gmail.com
            </a>
        </p>
    </div>
</div>

@vite('resources/js/success-animation.js')
</body>
</html>
