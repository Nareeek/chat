<?php

use App\Http\Controllers\Api\ChatApiController;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $users = User::all();
    return view('dashboard', ['users' => $users]);
})->middleware(['auth'])->name('dashboard');


require __DIR__ . '/auth.php';

Route::middleware(['auth'])->prefix('api')->group(function () {
    Route::get('/chats', [ChatApiController::class, 'index'])->middleware('throttle:chat-search');
    Route::get('/chats/{chat}/messages', [ChatApiController::class, 'messages'])->whereNumber('chat');
    Route::post('/chats/{chat}/messages', [ChatApiController::class, 'storeMessage'])
        ->whereNumber('chat')
        ->middleware('throttle:message-send');
    Route::post('/direct-chats', [ChatApiController::class, 'createDirectChat'])->middleware('throttle:room-actions');
    Route::post('/rooms', [ChatApiController::class, 'createRoom'])->middleware('throttle:room-actions');
    Route::post('/rooms/{chat}/join', [ChatApiController::class, 'joinRoom'])
        ->whereNumber('chat')
        ->middleware('throttle:room-actions');
    Route::get('/search', [ChatApiController::class, 'search'])->middleware('throttle:chat-search');
    Route::post('/chats/{chat}/assistant', [ChatApiController::class, 'inviteAssistant'])
        ->whereNumber('chat')
        ->middleware('throttle:room-actions');
});
