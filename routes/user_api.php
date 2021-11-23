<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::get('EmailConfirmation/{email}/{hash}', [UserController::class, 'verify']);

Route::middleware(['myauths'])->group(function () {
    // USER ROUTES
    Route::post('/UpdateUser/{id}', [UserController::class, 'update']);
    Route::post('/UpdatePassword', [UserController::class, 'update_password']);
    Route::post('/logout', [UserController::class, 'logout']);
});
