<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function() {

    Route::prefix('chat')->name('chat.')->middleware('auth:sanctum')->group(function() {
        Route::post('/', [App\Http\Controllers\Api\v1\AiMessageController::class, 'send'])->name('send-chat');
    });

});