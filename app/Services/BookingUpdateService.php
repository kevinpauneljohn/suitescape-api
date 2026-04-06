<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use App\Services\BookingRefundProcessService;
use App\Services\BookingCreateService;
use App\Services\BookingCancellationService;
use App\Services\ConstantService;
use App\Services\UnavailableDateService;
use App\Services\NotificationService;

class BookingUpdateService
{
    protected BookingCreateService $bookingCreateService;

    protected UnavailableDateService $unavailableDateService;

    protected BookingRefundProcessService $bookingRefundProcessService;

    protected BookingCancellationService $bookingCancellationService;

    protected ConstantService $constantService;

    protected NotificationService $notificationService;

    public function __construct(
        BookingCreateService $bookingCreateService,
        UnavailableDateService $unavailableDateService,
        BookingRefundProcessService $bookingRefundProcessService,
        BookingCancellationService $bookingCancellationService,
        ConstantService $constantService,
        NotificationService $notificationService
    ){
        $this->bookingCreateService = $bookingCreateService;
        $this->unavailableDateService = $unavailableDateService;
        $this->bookingRefundProcessService = $bookingRefundProcessService;
        $this->bookingCancellationService = $bookingCancellationService;
        $this->constantService = $constantService;
        $this->notificationService = $notificationService;
    }

    public function updateBookingInvoice($id, $invoiceData)
    {
        $invoice = Booking::findOrFail($id)->invoice;

        $invoice->update($invoiceData);

        return $invoice;
    }

    public function updateBookingStatus($id, $status, $message = null)
    {
        $booking = Booking::findOrFail($id);

        if ($status === 'cancelled') {
            $booking->update([
                'cancellation_reason' => $message,
            ]);

            $hostUserId = $booking->listing->user_id;
            $authUserId = auth()->id();
            $cancelledByHost = $hostUserId && $authUserId === $hostUserId;
            $reasonSuffix = $message ? " Reason: \"{$message}\"." : '';

            if ($cancelledByHost) {
                // Host cancelled the guest's booking — notify the guest
                $this->notificationService->createNotification([
                    'user_id' => $booking->user_id,
                    'title' => 'Your Booking Was Cancelled by the Host',
                    'message' => "Your booking for \"{$booking->listing->name}\" ({$booking->date_start->format('M d, Y')} - {$booking->date_end->format('M d, Y')}) was cancelled by the host.{$reasonSuffix}",
                    'type' => 'booking_cancelled',
                    'action_id' => $booking->id,
                ]);
            } else {
                // Guest cancelled — notify the guest
                $this->notificationService->createNotification([
                    'user_id' => $booking->user_id,
                    'title' => 'Booking Cancelled',
                    'message' => "Your booking for \"{$booking->listing->name}\" has been cancelled.",
                    'type' => 'booking_cancelled',
                    'action_id' => $booking->id,
                ]);

                // Notify the host
                if ($hostUserId && $hostUserId !== $booking->user_id) {
                    $guestName = $booking->user->firstname . ' ' . $booking->user->lastname;
                    $this->notificationService->createNotification([
                        'user_id' => $hostUserId,
                        'title' => 'Booking Cancelled',
                        'message' => "{$guestName} has cancelled their booking for \"{$booking->listing->name}\" ({$booking->date_start->format('M d, Y')} - {$booking->date_end->format('M d, Y')}).",
                        'type' => 'host_booking_cancelled',
                        'action_id' => $booking->id,
                    ]);
                }
            }

            if (isset($booking->invoice->payment_id)) {
                try {
                    $paymentId = $booking->invoice->payment_id;

                    // calculateCancellationFee() returns ONLY the per-day portion.
                    // We add the platform fee (cancellation_fee constant) once here.
                    // Within the free cancellation window: per-day = 0, platform fee still applies.
                    $perDayFee = $this->bookingCancellationService->calculateCancellationFee($booking);
                    $platformFee = (float) ($this->constantService->getConstant('cancellation_fee')->value ?? 0);
                    $totalCancellationFee = $perDayFee + $platformFee;
                    $cancellationFeeCentavos = (int) round($totalCancellationFee * 100);

                    // ── Refund original booking payment ───────────────────────────────
                    // Fetch the actual amount charged on the original PayMongo payment.
                    // booking->amount may have been updated after a rebook (higher new amount),
                    // so using it directly would exceed what was actually charged and cause
                    // PayMongo to reject the refund with "parameter_above_maximum".
                    $chargedCentavos = $this->bookingRefundProcessService->getPaymentAmount($paymentId)
                        ?? (int) round($booking->amount * 100);

                    // Apply cancellation fee only to the original payment; the rebook
                    // additional payment is always refunded in full.
                    $originalRefund = max(0, $chargedCentavos - $cancellationFeeCentavos);

                    if ($originalRefund > 0) {
                        $refundResponse = $this->bookingRefundProcessService->refundPayment($paymentId, $originalRefund);
                        if ($refundResponse['status'] === 'success') {
                            $this->bookingRefundProcessService->createBookingCancellation($booking->id, $booking->user_id, $refundResponse['data']);
                            \Log::info('Booking original payment refunded', [
                                'booking_id'    => $booking->id,
                                'refund_amount' => $originalRefund / 100,
                            ]);
                        } else {
                            \Log::error('Refund failed but continuing with cancellation', [
                                'booking_id'      => $booking->id,
                                'refund_response' => $refundResponse,
                            ]);
                        }
                    }

                    // ── Refund rebook additional payment (if any) ─────────────────────
                    $rebookRequest = $booking->rebookRequests()
                        ->where('payment_status', 'paid')
                        ->whereNotNull('rebook_payment_id')
                        ->latest()
                        ->first();

                    if ($rebookRequest && $rebookRequest->rebook_payment_id) {
                        $rebookChargedCentavos = $this->bookingRefundProcessService->getPaymentAmount($rebookRequest->rebook_payment_id)
                            ?? (int) round($rebookRequest->difference * 100);

                        if ($rebookChargedCentavos > 0) {
                            $rebookRefundResponse = $this->bookingRefundProcessService->refundPayment($rebookRequest->rebook_payment_id, $rebookChargedCentavos);
                            if ($rebookRefundResponse['status'] === 'success') {
                                \Log::info('Rebook additional payment refunded', [
                                    'booking_id'         => $booking->id,
                                    'rebook_request_id'  => $rebookRequest->id,
                                    'refund_amount'      => $rebookChargedCentavos / 100,
                                ]);
                            } else {
                                \Log::error('Rebook additional payment refund failed', [
                                    'booking_id'         => $booking->id,
                                    'rebook_request_id'  => $rebookRequest->id,
                                    'refund_response'    => $rebookRefundResponse,
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error processing booking refund', [
                        'booking_id' => $booking->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // Remove unavailable dates when booking is cancelled
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);
        }

        $booking->update([
            'status' => $status,
        ]);

        return $booking;
    }

    /**
     * @throws \Exception
     */
    public function updateBookingDates($id, $startDate, $endDate, $updateDatesKey)
    {
        $booking = Booking::findOrFail($id);

        // Update booking room dates and status
        $newStatus = Carbon::today()->betweenIncluded($startDate, $endDate) ? 'ongoing' : 'upcoming';
        $booking->update([
            'date_start' => $startDate,
            'date_end' => $endDate,
            'status' => $newStatus
        ]);

        // Reset additional payment status without the update dates key
        $this->resetAdditionalPayments($booking, $updateDatesKey);

        // Update booking amount
        $this->updateBookingAmount($booking, $startDate, $endDate);

        // Update unavailable dates for paid bookings
        $this->updateUnavailableDates($booking);

        return $booking;
    }

    private function resetAdditionalPayments($booking, $updateDatesKey)
    {
        $paidAdditionalPayments = collect($booking->invoice->paid_additional_payments);
        $indexToRemove = $paidAdditionalPayments->search(fn($payment) => $payment === $updateDatesKey);

        if ($indexToRemove !== false) {
            $booking->invoice->update([
                'paid_additional_payments' => $paidAdditionalPayments->forget($indexToRemove)->toArray(),
            ]);
        }
    }

    private function updateBookingAmount($booking, $startDate, $endDate): void
    {
        $amount = $this->bookingCreateService->calculateAmount(
            $booking->listing,
            $booking->bookingRooms,
            $booking->bookingAddons,
            $booking->coupon,
            $startDate,
            $endDate
        );

        $booking->update([
            'amount' => $amount['total'],
            'base_amount' => $amount['base'],
        ]);
    }

    private function updateUnavailableDates($booking)
    {
        if ($booking->invoice->payment_status === 'paid') {
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);

            // Add unavailable dates for the booking
            if ($booking->listing->is_entire_place) {
                $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'listing', $booking->listing->id, $booking->date_start, $booking->date_end);
            } else {
                foreach ($booking->rooms as $room) {
                    $this->unavailableDateService->addUnavailableDatesForBooking($booking, 'room', $room->id, $booking->date_start, $booking->date_end);
                }
            }
        }
    }
}
