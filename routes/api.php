<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\RequestController;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::get('EmailConfirmation/{email}/{hash}', [UserController::class, 'verify']);

Route::middleware(['myauths'])->group(function () {
    // USER ROUTES
    Route::post('/UpdateUser/{id}', [UserController::class, 'update']);
    Route::post('/UpdatePassword', [UserController::class, 'update_password']);
    Route::post('/logout', [UserController::class, 'logout']);

    //POST ROUTES
    Route::post('/CreatePost', [PostController::class, 'create']);
    Route::post('/UpdatePost/{id}', [PostController::class, 'update']);
    Route::delete('/DeletePost/{id}', [PostController::class, 'delete']);
    Route::get('/GetPublicPosts', [PostController::class, 'getPublicposts']);
    Route::get('/GetPrivatePosts', [PostController::class, 'getPrivateposts']);
    Route::post('/SearchPost', [PostController::class, 'search']);

    // FRIEND REQUEST ROUTES
    Route::post('/AllUsers', [RequestController::class, 'getAllusers']);
    Route::post('/SendRequest', [RequestController::class, 'sendRequest']);
    Route::get('/GetRequests', [RequestController::class, 'getRequests']);
    Route::post('/RecieveRequest', [RequestController::class, 'recieveRequest']);
    Route::delete('/RemoveFriend/{id}', [RequestController::class, 'remove']);

    //COMMENTS ROUTES
    Route::post('/CreateComment', [CommentController::class, 'create']);
    Route::post('/UpdateComment/{id}', [CommentController::class, 'update']);
    Route::delete('/DeleteComment/{id}', [CommentController::class, 'delete']);
});
