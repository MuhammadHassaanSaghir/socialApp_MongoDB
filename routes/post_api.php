<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;

Route::middleware(['myauths'])->group(function () {
    //POST ROUTES
    Route::post('/CreatePost', [PostController::class, 'create']);
    Route::post('/UpdatePost/{id}', [PostController::class, 'update']);
    Route::delete('/DeletePost/{id}', [PostController::class, 'delete']);
    Route::get('/GetPublicPosts', [PostController::class, 'getPublicposts']);
    Route::get('/GetPrivatePosts', [PostController::class, 'getPrivateposts']);
    Route::post('/SearchPost', [PostController::class, 'search']);
});
