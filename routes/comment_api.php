<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommentController;

Route::middleware(['myauths'])->group(function () {

    //COMMENTS ROUTES
    Route::post('/CreateComment', [CommentController::class, 'create']);
    Route::post('/UpdateComment/{id}', [CommentController::class, 'update']);
    Route::delete('/DeleteComment/{id}', [CommentController::class, 'delete']);
});
