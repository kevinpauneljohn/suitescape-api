<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;

class BookingUpdateService
{
    protected BookingCancellationService $bookingCancellationService;

    protected BookingCreateService $bookingCreateService;

    protected ConstantService $constantService;

    protected MailService $mailService;

    protected PaymentService $paymentService;

    protected UnavailableDateService $unavailableDateService;

    public function __construct(BookingCancellationService $bookingCancellationService, BookingCreateService $bookingCreateService, ConstantService $constantService, MailService $mailService, PaymentService $paymentService, UnavailableDateService $unavailableDateService)
    {
        $this->bookingCancellationService = $bookingCancellationService;
        $this->bookingCreateService = $bookingCreateService;
        $this->constantService = $constantService;
        $this->mailService = $mailService;
        $this->paymentService = $paymentService;
        $this->unavailableDateService = $unavailableDateService;
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
            $this->unavailableDateService->removeUnavailableDatesForBooking($booking);

            $booking->update([
                'cancellation_reason' => $message,
            ]);

            $this->paymentService->archivePaymentLink($booking->invoice->reference_number);
            $this->mailService->sendBookingCancelledEmails($booking);
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
