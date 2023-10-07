<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'You need to be logged in to do that.'
                ], 401);
            }
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/listings/*/videos/*')) {
                return response()->json([
                    'message' => 'Video not found.'
                ], 404);
            }
            if ($request->is('api/listings/*/images/*')) {
                return response()->json([
                    'message' => 'Image not found.'
                ], 404);
            }
            if ($request->is('api/listings/*')) {
                return response()->json([
                    'message' => 'Listing not found.'
                ], 404);
            }
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Error finding this resource.'
                ], 404);
            }
        });
    }
}
