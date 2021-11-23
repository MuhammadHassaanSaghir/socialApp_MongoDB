<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommentController;

Route::middleware(['myauths'])->group(function () {
    // FRIEND REQUEST ROUTES
    Route::post('/AllUsers', [RequestController::class, 'getAllusers']);
    Route::post('/SendRequest', [RequestController::class, 'sendRequest']);
    Route::get('/GetRequests', [RequestController::class, 'getRequests']);
    Route::post('/RecieveRequest', [RequestController::class, 'recieveRequest']);
    Route::delete('/RemoveFriend/{id}', [RequestController::class, 'remove']);
});
