<?php

use App\Http\Controllers\API\RegistrationController;
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
    })->name("user");
});

Route::post('/register', [RegistrationController::class, "register"])->name("register");
Route::post('/login', [RegistrationController::class, "login"])->name("login");
Route::post('/logout', [RegistrationController::class, "logout"])->name("logout");

Route::post('/videos', [VideoController::class, "uploadVideo"])->name("videos.upload");
Route::get('/videos', [VideoController::class, "getAllVideos"])->name("videos.all");
Route::get('/videos/{id}', [VideoController::class, "streamVideo"])->name("videos.stream");
Route::post('/videos/{id}/like', [VideoController::class, "likeVideo"])->name("videos.like");
Route::post('/videos/{id}/save', [VideoController::class, "saveVideo"])->name("videos.save");
