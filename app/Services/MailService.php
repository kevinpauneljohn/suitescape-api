<?php

namespace App\Services;

use App\Mail\BookingCancelledHost;
use App\Mail\BookingCancelledUser;
use App\Mail\BookingCompletedHost;
use App\Mail\BookingCompletedUser;
use App\Mail\ResetPassword;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

class MailService
{
    protected BookingCancellationService $bookingCancellationService;

    protected ConstantService $constantService;

    public function __construct(BookingCancellationService $bookingCancellationService, ConstantService $constantService)
    {
        $this->bookingCancellationService = $bookingCancellationService;
        $this->constantService = $constantService;
    }

    public function sendResetToken($email, $token): void
    {
        Mail::to($email)->send(new ResetPassword($token));
    }

    public function sendBookingCompletedEmails(Booking $booking)
    {
        // Send email to the host
        Mail::to($booking->listing->user->email)->send(new BookingCompletedHost($booking));

        // Send email to the user
        Mail::to($booking->user->email)->send(new BookingCompletedUser($booking));
    }

    public function sendBookingCancelledEmails(Booking $booking)
    {
        if ($booking->status !== 'cancelled') {
            return;
        }

        // Get cancellation fees and policy
        $cancellationFee = floatval($this->bookingCancellationService->calculateCancellationFee($booking));
        $suitescapeCancellationFee = floatval($this->constantService->getConstant('cancellation_fee')->value);
        $cancellationPolicy = $this->constantService->getConstant('cancellation_policy')->value;

        // Send email to the host
        Mail::to($booking->listing->user->email)->send(new BookingCancelledHost($booking, $suitescapeCancellationFee, $cancellationFee, $cancellationPolicy));

        // Send email to the user
        Mail::to($booking->user->email)->send(new BookingCancelledUser($booking, $suitescapeCancellationFee, $cancellationFee, $cancellationPolicy));
    }
}
