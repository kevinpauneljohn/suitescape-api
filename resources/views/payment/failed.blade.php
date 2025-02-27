<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - Suitescape PH</title>
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
        <div class="icon-container error">
            <svg class="icon error" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <h1 class="title">Payment Failed</h1>

        <p class="description">
            We were unable to process your payment. Please try again or use a different payment method.
        </p>

        <div class="payment-details">
            <div class="payment-detail-row">
                <span class="payment-detail-label">Booking Reference</span>
                <span class="payment-detail-value">{{ $booking->id }}</span>
            </div>
            <div class="payment-detail-row">
                <span class="payment-detail-label">Property</span>
                <span class="payment-detail-value">{{ $booking->listing->name }}</span>
            </div>
            <div class="payment-detail-row">
                <span class="payment-detail-label">Amount</span>
                <span class="payment-detail-value">₱{{ number_format($booking->amount, 2) }}</span>
            </div>
{{--            @if($error_code)--}}
{{--                <div class="payment-detail-row">--}}
{{--                    <span class="payment-detail-label">Error Code</span>--}}
{{--                    <span class="payment-detail-value">{{ $error_code }}</span>--}}
{{--                </div>--}}
{{--            @endif--}}
{{--            @if($error_message)--}}
{{--                <div class="payment-detail-row">--}}
{{--                    <span class="payment-detail-label">Error Message</span>--}}
{{--                    <span class="payment-detail-value">{{ $error_message }}</span>--}}
{{--                </div>--}}
{{--            @endif--}}
        </div>

        <p class="support-text">
            Need assistance? Contact us at<br>
            <a href="mailto:suitescape.ph.2024@gmail.com" class="support-email">
                suitescape.ph.2024@gmail.com
            </a>
        </p>
    </div>
</div>
</body>
</html>
