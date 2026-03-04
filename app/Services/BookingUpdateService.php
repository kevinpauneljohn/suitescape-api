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

            // Notify the guest about the cancellation
            $this->notificationService->createNotification([
                'user_id' => $booking->user_id,
                'title' => 'Booking Cancelled',
                'message' => "Your booking for \"{$booking->listing->name}\" has been cancelled.",
                'type' => 'booking_cancelled',
                'action_id' => $booking->id,
            ]);

            // Notify the host about the cancellation
            $hostUserId = $booking->listing->host->user_id;
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

            if (isset($booking->invoice->payment_id)) {
                try {
                    $paymentId = $booking->invoice->payment_id;
                    $cancellationFee = $this->bookingCancellationService->calculateCancellationFee($booking);
                    $suitescapeFee = $this->constantService->getConstant('cancellation_fee')->value ?? 0;

                    $totalCancellationFee = $cancellationFee + $suitescapeFee;
                    // Convert totalAmount to centavos for PayMongo
                    $refundAmount = ($booking->amount - $totalCancellationFee) * 100;
                    
                    // Only process refund if there's an amount to refund
                    if ($refundAmount > 0) {
                        $refundResponse = $this->bookingRefundProcessService->refundPayment($paymentId, (int) $refundAmount);
                        if ($refundResponse['status'] === 'success') {
                            $this->bookingRefundProcessService->createBookingCancellation($booking->id, $booking->user_id, $refundResponse['data']);
                            \Log::info('Booking refund processed successfully', [
                                'booking_id' => $booking->id,
                                'refund_amount' => $refundAmount / 100,
                            ]);
                        } else {
                            \Log::error('Refund failed but continuing with cancellation', [
                                'booking_id' => $booking->id,
                                'refund_response' => $refundResponse,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Log the error but don't prevent cancellation
                    \Log::error('Error processing booking refund', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
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
