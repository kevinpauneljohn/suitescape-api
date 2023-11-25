<?php

use App\Http\Controllers\API\ListingController;
use App\Http\Controllers\API\RegistrationController;
use App\Http\Controllers\API\VideoFeedController;
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
    })->name("user");
});

Route::post('/register', [RegistrationController::class, "register"])->name("register");
Route::post('/login', [RegistrationController::class, "login"])->name("login");
Route::post('/logout', [RegistrationController::class, "logout"])->name("logout");

Route::prefix('videos')->group(function () {
    Route::get('/', [VideoFeedController::class, "getVideoFeed"])->name("videos.feed");
});

Route::prefix('listings')->group(function () {
    Route::get('/', [ListingController::class, "getAllListings"])->name("listings.all");
    Route::get('/{id}', [ListingController::class, "getListing"])->name("listings.get");

    Route::prefix('{id}')->group(function () {
        Route::get('/host', [ListingController::class, "getListingHost"])->name("listings.host");
        Route::get('/images', [ListingController::class, "getListingImages"])->name("listings.images");
        Route::get('/videos', [ListingController::class, "getListingVideos"])->name("listings.videos");
        Route::get('/reviews', [ListingController::class, "getListingReviews"])->name("listings.reviews");

        Route::get('/images/{imageId}', [ListingController::class, "getListingImage"])->name("listings.images.get");
        Route::get('/videos/{videoId}', [ListingController::class, "getListingVideo"])->name("listings.videos.get");

        Route::post('/images', [ListingController::class, "uploadListingImage"])->name("listings.images.upload");
        Route::post('/videos', [ListingController::class, "uploadListingVideo"])->name("listings.videos.upload");
        Route::post('/like', [ListingController::class, "likeListing"])->name("listings.like");
        Route::post('/save', [ListingController::class, "saveListing"])->name("listings.save");
    });
});
