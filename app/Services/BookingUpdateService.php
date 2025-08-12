<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use App\Services\BookingRefundProcessService;
use App\Services\BookingCreateService;
use App\Services\BookingCancellationService;
use App\Services\ConstantService;
use App\Services\UnavailableDateService;

class BookingUpdateService
{
    protected BookingCreateService $bookingCreateService;

    protected UnavailableDateService $unavailableDateService;

    protected BookingRefundProcessService $bookingRefundProcessService;

    protected BookingCancellationService $bookingCancellationService;

    protected ConstantService $constantService;

    public function __construct(
        BookingCreateService $bookingCreateService,
        UnavailableDateService $unavailableDateService,
        BookingRefundProcessService $bookingRefundProcessService,
        BookingCancellationService $bookingCancellationService,
        ConstantService $constantService
    ){
        $this->bookingCreateService = $bookingCreateService;
        $this->unavailableDateService = $unavailableDateService;
        $this->bookingRefundProcessService = $bookingRefundProcessService;
        $this->bookingCancellationService = $bookingCancellationService;
        $this->constantService = $constantService;
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
            // $this->unavailableDateService->removeUnavailableDatesForBooking($booking);

            $booking->update([
                'cancellation_reason' => $message,
            ]);

            if (isset($booking->invoice->payment_id)) {
                try {
                    $paymentId = $booking->invoice->payment_id;
                    $cancellationFee = $this->bookingCancellationService->calculateCancellationFee($booking);
                    $suitescapeFee = $this->constantService->getConstant('cancellation_fee')->value;

                    $totalCancellationFee = $cancellationFee + $suitescapeFee;
                    $totalAmount = $booking->amount - $totalCancellationFee;
                    //convert totalAmount to centavos
                    $totalAmount = ($booking->amount - $totalCancellationFee) * 100;
                    $refundResponse = $this->bookingRefundProcessService->refundPayment($paymentId, $totalAmount);
                    if ($refundResponse['status'] === 'success') {
                        $createBookingCancellation = $this->bookingRefundProcessService->createBookingCancellation($booking->id, $booking->user_id, $refundResponse['data']);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error processing booking cancellation', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e; // Re-throw the exception to be handled by the caller
                }
            }
        }

        // $booking->update([
        //     'status' => $status,
        // ]);

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
