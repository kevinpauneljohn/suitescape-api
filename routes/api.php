<?php

use App\Http\Controllers\API\AppFeedbackController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\ConstantController;
use App\Http\Controllers\API\EarningsController;
use App\Http\Controllers\API\HostController;
use App\Http\Controllers\API\ImageController;
use App\Http\Controllers\API\ListingController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PayoutController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\RegistrationController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\VideoController;
use App\Http\Controllers\API\PaymongoWebhookController;
use App\Http\Controllers\API\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// require __DIR__ . '/auth.php';

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->name('user');
});

Route::post('/register', [RegistrationController::class, 'register'])->name('register');
Route::post('/login', [RegistrationController::class, 'login'])->name('login');
Route::post('/forgot-password', [RegistrationController::class, 'forgotPassword'])->name('password.email');
Route::post('/validate-reset-token', [RegistrationController::class, 'validateResetToken'])->name('password.reset.validate');
Route::post('/reset-password', [RegistrationController::class, 'resetPassword'])->name('password.reset');
Route::post('/logout', [RegistrationController::class, 'logout'])->name('logout');

Route::post('/app-feedback', [AppFeedbackController::class, 'createAppFeedback'])->name('app.feedback');

Route::prefix('email')->group(function () {
    Route::get('/verify', [RegistrationController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('/resend', [RegistrationController::class, 'resendEmail'])->name('verification.resend');
});

Route::prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'getProfile'])->name('profile.get');
    Route::get('/saved', [ProfileController::class, 'getSavedListings'])->name('profile.saves');
    Route::get('/liked', [ProfileController::class, 'getLikedListings'])->name('profile.likes');
    Route::get('/viewed', [ProfileController::class, 'getViewedListings'])->name('profile.views');

    Route::post('/validate', [ProfileController::class, 'validateProfile'])->name('profile.validate');
    Route::post('/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::post('/update-password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');
    Route::post('/update-active-session', [ProfileController::class, 'updateActiveSession'])->name('profile.update-active-session');
});

Route::prefix('constants')->group(function () {
    Route::get('/', [ConstantController::class, 'getAllConstants'])->name('constants.all');
    Route::get('/{key}', [ConstantController::class, 'getConstant'])->name('constants.get');
    Route::post('/{key}', [ConstantController::class, 'updateConstant'])->name('constants.update');
});

Route::prefix('videos')->group(function () {
    Route::get('/', [VideoController::class, 'getAllVideos'])->name('videos.all');
    Route::get('/feed', [VideoController::class, 'getVideoFeed'])->name('videos.feed');
    Route::get('/{id}', [VideoController::class, 'getVideo'])->name('videos.get')->whereUuid('id');
    Route::post('/upload', [VideoController::class, 'uploadVideo'])->name('videos.upload');
    Route::post('/{id}/approve', [VideoController::class, 'approveVideo'])->name('videos.approve')->whereUuid('id');
    Route::get('/content-moderation', [VideoController::class, 'getContentModeration'])->name('videos.content-moderation');
    Route::put('/{id}/status', [VideoController::class, 'updateStatus'])->name('videos.update-status')->whereUuid('id');
    Route::get('/video-violations', [VideoController::class, 'violations'])->name('videos.violations');
});

Route::prefix('images')->group(function () {
    Route::get('/', [ImageController::class, 'getAllImages'])->name('images.all');
    Route::get('/{id}', [ImageController::class, 'getImage'])->name('images.get')->whereUuid('id');
    Route::post('/upload', [ImageController::class, 'uploadImage'])->name('images.upload');
});

Route::prefix('rooms')->group(function () {
    Route::get('/', [RoomController::class, 'getAllRooms'])->name('rooms.all');
    Route::get('/{id}', [RoomController::class, 'getRoom'])->name('rooms.get')->whereUuid('id');

    Route::prefix('{id}')->group(function () {
        Route::get('/listing', [RoomController::class, 'getRoomListing'])->name('rooms.listing');
        Route::get('/unavailable-dates', [RoomController::class, 'getUnavailableDates'])->name('rooms.unavailable-dates');

        Route::post('/add-special-rate', [RoomController::class, 'addSpecialRate'])->name('rooms.add-special-rate');
        Route::post('/update-special-rate', [RoomController::class, 'updateSpecialRate'])->name('rooms.update-special-rate');
        Route::post('/remove-special-rate', [RoomController::class, 'removeSpecialRate'])->name('rooms.remove-special-rate');
        Route::post('/block-dates', [RoomController::class, 'blockDates'])->name('rooms.block-dates');
        Route::post('/unblock-dates', [RoomController::class, 'unblockDates'])->name('rooms.unblock-dates');
        Route::post('/update-prices', [RoomController::class, 'updatePrices'])->name('rooms.update-prices');
    })->whereUuid('id');
});

Route::prefix('listings')->group(function () {
    Route::middleware('throttle:1000,1')->group(function () {
        Route::get('/search', [ListingController::class, 'searchListings'])->name('listings.search');
    });

    Route::get('/', [ListingController::class, 'getAllListings'])->name('listings.all');
    Route::get('/{id}', [ListingController::class, 'getListing'])->name('listings.get')->whereUuid('id');
    Route::get('/host', [ListingController::class, 'getListingsByHost'])->name('listings.user')->whereUuid('id');
    Route::post('/', [ListingController::class, 'createListing'])->name('listings.create');
    Route::post('/{id}', [ListingController::class, 'updateListing'])->name('listings.update')->whereUuid('id');
    Route::delete('/{id}', [ListingController::class, 'deleteListing'])->name('listings.delete')->whereUuid('id');

    Route::prefix('{id}')->group(function () {
        //        Route::get('/host', [ListingController::class, 'getListingHost'])->name('listings.host');
        Route::get('/rooms', [ListingController::class, 'getListingRooms'])->name('listings.rooms');
        Route::get('/images', [ListingController::class, 'getListingImages'])->name('listings.images');
        Route::get('/videos', [ListingController::class, 'getListingVideos'])->name('listings.videos');
        Route::get('/reviews', [ListingController::class, 'getListingReviews'])->name('listings.reviews');
        Route::get('/unavailable-dates', [ListingController::class, 'getUnavailableDates'])->name('listings.unavailable-dates');

        Route::post('/like', [ListingController::class, 'likeListing'])->name('listings.like');
        Route::post('/save', [ListingController::class, 'saveListing'])->name('listings.save');
        Route::post('/view', [ListingController::class, 'viewListing'])->name('listings.view');

        Route::post('/add-special-rate', [ListingController::class, 'addSpecialRate'])->name('listings.add-special-rate');
        Route::post('/update-special-rate', [ListingController::class, 'updateSpecialRate'])->name('listings.update-special-rate');
        Route::post('/remove-special-rate', [ListingController::class, 'removeSpecialRate'])->name('listings.remove-special-rate');
        Route::post('/block-dates', [ListingController::class, 'blockDates'])->name('listings.block-dates');
        Route::post('/unblock-dates', [ListingController::class, 'unblockDates'])->name('listings.unblock-dates');
        Route::post('/update-prices', [ListingController::class, 'updatePrices'])->name('listings.update-prices');

        Route::post('/images/upload', [ListingController::class, 'uploadListingImage'])->name('listings.images.upload');
        Route::post('/videos/upload', [ListingController::class, 'uploadListingVideo'])->name('listings.videos.upload');
    })->whereUuid('id');
});

Route::prefix('hosts')->group(function () {
    Route::get('/', [HostController::class, 'getAllHosts'])->name('hosts.all');
    Route::get('/{id}', [HostController::class, 'getHost'])->name('hosts.get')->whereUuid('id');

    Route::prefix('{id}')->group(function () {
        Route::get('/listings', [HostController::class, 'getHostListings'])->name('hosts.listings');
        Route::get('/reviews', [HostController::class, 'getHostReviews'])->name('hosts.reviews');
        Route::get('/likes', [HostController::class, 'getHostLikes'])->name('hosts.likes');
        Route::get('/saves', [HostController::class, 'getHostSaves'])->name('hosts.saves');
        Route::get('/views', [HostController::class, 'getHostViews'])->name('hosts.views');
    })->whereUuid('id');
});

Route::prefix('bookings')->group(function () {
    Route::get('/', [BookingController::class, 'getAllBookings'])->name('bookings.all');
    Route::get('/user', [BookingController::class, 'getUserBookings'])->name('bookings.user');
    Route::get('/host', [BookingController::class, 'getHostBookings'])->name('bookings.host');
    Route::get('/{id}', [BookingController::class, 'getBooking'])->name('bookings.get')->whereUuid('id');
    Route::get('/{id}/amount', [BookingController::class, 'getBookingAmount'])->name('bookings.amount')->whereUuid('id');
    Route::post('/', [BookingController::class, 'createBooking'])->name('bookings.create');
    Route::post('/{id}/update-status', [BookingController::class, 'updateBookingStatus'])->name('bookings.update-status')->whereUuid('id');
    Route::post('/{id}/update-dates', [BookingController::class, 'updateBookingDates'])->name('bookings.update-dates')->whereUuid('id');
});

Route::prefix('reviews')->group(function () {
    Route::get('/', [ReviewController::class, 'getAllReviews'])->name('reviews.all');
    Route::get('/{id}', [ReviewController::class, 'getReview'])->name('reviews.get')->whereUuid('id');
    Route::post('/', [ReviewController::class, 'createReview'])->name('reviews.create');
});

Route::prefix('packages')->group(function () {
    Route::get('/', [PackageController::class, 'getAllPackages'])->name('packages.all');
    Route::get('/{id}', [PackageController::class, 'getPackage'])->name('packages.get')->whereUuid('id');
});

Route::prefix('messages')->group(function () {
    Route::middleware('throttle:1000,1')->group(function () {
        Route::get('/search', [ChatController::class, 'searchChats'])->name('chat.search');
        Route::post('/send', [ChatController::class, 'sendMessage'])->name('chat.send');
        Route::get('/', [ChatController::class, 'getAllChats'])->name('chat.all');
    });

    
    Route::get('/{id}', [ChatController::class, 'getAllMessages'])->name('chat.get')->whereUuid('id');
    Route::post('/send', [ChatController::class, 'sendMessage'])->name('chat.send');
});

Route::prefix('earnings')->group(function () {
    Route::get('/{year}', [EarningsController::class, 'getYearlyEarnings'])->name('earnings.yearly')->whereNumber('year');
    Route::get('/years', [EarningsController::class, 'getAvailableYears'])->name('earnings.available-years');
});

// Route::middleware('paymongo.signature')->prefix('paymongo')->group(function () {
Route::prefix('paymongo')->group(function () {
    Route::post('/link-paid', [PaymentController::class, 'linkPaymentPaid'])->name('paymongo.link-paid');
    Route::post('/source-chargeable', [PaymentController::class, 'sourceChargeable'])->name('paymongo.source-chargeable');
});

Route::prefix('customer')->group(function () {
    Route::get('/', [PaymentController::class, 'getCustomer'])->name('payment.customer');
    Route::delete('/', [PaymentController::class, 'deleteCustomer'])->name('payment.customer.delete');
});

Route::prefix('payment')->group(function () {
    Route::get('/link/{id}', [PaymentController::class, 'getPaymentLink'])->name('payment.link');
    Route::get('/source/{id}', [PaymentController::class, 'getPaymentSource'])->name('payment.source');
    Route::get('/intent/{id}', [PaymentController::class, 'getPaymentIntent'])->name('payment.intent');
    Route::get('/methods', [PaymentController::class, 'getPaymentMethods'])->name('payment.methods');
    Route::get('/success', [PaymentController::class, 'paymentSuccessStatus'])->name('payment.success-status');
    Route::get('/failed', [PaymentController::class, 'paymentFailedStatus'])->name('payment.failed-status');
    Route::post('/', [PaymentController::class, 'createPayment'])->name('payment.create');
    Route::post('/link', [PaymentController::class, 'createPaymentLink'])->name('payment.link.create');
    // Route::post('/source', [PaymentController::class, 'createPaymentSource'])->name('payment.source');

    //for testing only
    Route::post('/pay/gcash', [PaymentController::class, 'payWithGcash']);
});

Route::prefix('payout')->group(function () {
    Route::get('/methods', [PayoutController::class, 'getPayoutMethods'])->name('payout.methods');
    Route::post('/', [PayoutController::class, 'addPayoutMethod'])->name('payout.add');
    Route::post('/{id}', [PayoutController::class, 'updatePayoutMethod'])->name('payout.update')->whereUuid('id');
    Route::delete('/{id}', [PayoutController::class, 'deletePayoutMethod'])->name('payout.delete')->whereUuid('id');
    Route::get('/{id}', [PayoutController::class, 'getUserPayoutMethod'])->name('payout.user.info')->whereUuid('id');
});

Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'getUserNotifications'])->name('notifications.user');
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read')->whereUuid('id');
});

Route::prefix('booking_payments')->group(function () {
    Route::post('/', [PaymentController::class, 'cardPayment'])->name('booking.payments.card.payment');
    Route::post('/epayment', [PaymentController::class, 'ePayment'])->name('booking.payments.e-payment');
    Route::post('/epayment-chargeable', [PaymentController::class, 'ePaymentChargeable'])->name('booking.payments.e-payment-chargeable');
    Route::get('/source/{id}', [PaymentController::class, 'getBookingPaymentSource'])->name('booking.payment.source');
});

//paymentStatusCheck webhook
Route::prefix('webhook')->group(function () {
    Route::post('/paymongo/payment-status-check', [PaymongoWebhookController::class, 'ePaymentStatusCheck'])->name('webhook.paymongo.payment-status-check');
    Route::post('/paymongo/gcash-and-grab', [PaymongoWebhookController::class, 'gcashAndGrabWebhook'])->name('webhook.paymongo.gcash-and-grab');
    Route::post('/paymongo/refund-payment', [PaymongoWebhookController::class, 'refundPaymentWebhook'])->name('webhook.paymongo.refund');
    Route::post('/create', [WebhookController::class, 'store'])->name('webhook.create');
    Route::put('/update', [WebhookController::class, 'update'])->name('webhook.update');
    Route::get('/all', [WebhookController::class, 'showAllData'])->name('webhook.all');
    Route::get('/paymongo/all', [WebhookController::class, 'showAllFromPaymongo'])->name('webhook.paymongo.all');
    Route::get('/paymongo/{id}', [WebhookController::class, 'showApi'])->name('webhook.paymongo.show');
    Route::delete('/paymongo/{id}', [WebhookController::class, 'destroyApi'])->name('webhook.paymongo.destroy');
    Route::post('/paymongo/{id}/{status}', [WebhookController::class, 'changeStatus'])->name('webhook.paymongo.change-status');
});

Route::get('/discount', function() {
    return config('app.discount');
});

Route::get('/set-country', function() {
    return [
        'country' => config('app.default_country'),
        'allCountry' => config('app.all_country'),
    ];
});
