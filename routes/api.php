<?php

use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\ImageController;
use App\Http\Controllers\API\ListingController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\RegistrationController;
use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\SettingController;
use App\Http\Controllers\API\VideoController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->name('user');
});

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [RegistrationController::class, 'register'])->name('register');
    Route::post('/login', [RegistrationController::class, 'login'])->name('login');
});
Route::post('/logout', [RegistrationController::class, 'logout'])->name('logout');

Route::prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'getProfile'])->name('profile.get');
    Route::post('/', [ProfileController::class, 'updateProfile'])->name('profile.update');
});

Route::prefix('settings')->group(function () {
    Route::get('/', [SettingController::class, 'getAllSettings'])->name('settings.all');
    Route::get('/{key}', [SettingController::class, 'getSetting'])->name('settings.get');
    Route::post('/{key}', [SettingController::class, 'updateSetting'])->name('settings.update');
});

Route::prefix('videos')->group(function () {
    Route::get('/', [VideoController::class, 'getAllVideos'])->name('videos.all');
    Route::get('/feed', [VideoController::class, 'getVideoFeed'])->name('videos.feed');
    Route::get('/{id}', [VideoController::class, 'getVideo'])->name('videos.get')->whereUuid('id');
    Route::post('/upload', [VideoController::class, 'uploadVideo'])->name('videos.upload');
});

Route::prefix('images')->group(function () {
    Route::get('/', [ImageController::class, 'getAllImages'])->name('images.all');
    Route::get('/{id}', [ImageController::class, 'getImage'])->name('images.get')->whereUuid('id');
    Route::post('/upload', [ImageController::class, 'uploadImage'])->name('images.upload');
});

Route::prefix('rooms')->group(function () {
    Route::get('/', [RoomController::class, 'getAllRooms'])->name('rooms.all');
    Route::get('/{id}', [RoomController::class, 'getRoom'])->name('rooms.get')->whereUuid('id');
    Route::get('/{id}/listing', [RoomController::class, 'getRoomListing'])->name('rooms.listing')->whereUuid('id');
});

Route::prefix('listings')->group(function () {
    Route::get('/', [ListingController::class, 'getAllListings'])->name('listings.all');
    Route::get('/{id}', [ListingController::class, 'getListing'])->name('listings.get')->whereUuid('id');

    Route::prefix('{id}')->group(function () {
        Route::get('/host', [ListingController::class, 'getListingHost'])->name('listings.host');
        Route::get('/images', [ListingController::class, 'getListingImages'])->name('listings.images');
        Route::get('/videos', [ListingController::class, 'getListingVideos'])->name('listings.videos');
        Route::get('/reviews', [ListingController::class, 'getListingReviews'])->name('listings.reviews');
        Route::get('/rooms', [ListingController::class, 'getListingRooms'])->name('listings.rooms');

        Route::post('/like', [ListingController::class, 'likeListing'])->name('listings.like');
        Route::post('/save', [ListingController::class, 'saveListing'])->name('listings.save');

        Route::post('/images/upload', [ListingController::class, 'uploadListingImage'])->name('listings.images.upload');
        Route::post('/videos/upload', [ListingController::class, 'uploadListingVideo'])->name('listings.videos.upload');
    })->whereUuid('id');
});

Route::prefix('bookings')->group(function () {
    Route::get('/', [BookingController::class, 'getAllBookings'])->name('bookings.all');
    Route::post('/', [BookingController::class, 'createBooking'])->name('bookings.create');
});
