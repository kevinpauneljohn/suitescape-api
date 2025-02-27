<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Suitescape PH</title>
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
        <div class="icon-container {{ $isAlreadyVerified ? 'error' : 'success' }}">
            @if($isAlreadyVerified)
                <svg class="icon error" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            @else
                <svg class="icon success" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 13l4 4L19 7"/>
                </svg>
            @endif
        </div>

        <h1 class="title">
            @if($isAlreadyVerified)
                Email Already Verified
            @else
                Email Verified Successfully!
            @endif
        </h1>

        <p class="description">
            @if($isAlreadyVerified)
                Your email address has already been verified. You can now close this window.
            @else
                Thank you for verifying your email address. You can now close this window and continue using the application.
            @endif
        </p>

        <p class="support-text">
            Need assistance? Contact us at<br>
            <a href="mailto:suitescape.ph.2024@gmail.com" class="support-email">
                suitescape.ph.2024@gmail.com
            </a>
        </p>
    </div>
</div>

@if(!$isAlreadyVerified)
    @vite('resources/js/success-animation.js')
@endif
</body>
</html>
