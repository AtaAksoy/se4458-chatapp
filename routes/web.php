<?php

use App\Events\PublicTest;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test', function () {
    broadcast(new PublicTest('Mesaj buradan gönderildi!'));
    return 'Yayınlandı!';
});