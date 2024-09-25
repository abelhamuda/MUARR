<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;

Route::get('/', [EmployeeController::class, 'index'])->name('home');
Route::match(['get', 'post'], '/sso-process', [EmployeeController::class, 'ssoprocess'])->name('sso.process');
Route::get('/alphabit', [EmployeeController::class, 'alphabit'])->name('alphabit');
Route::get('/icore', [EmployeeController::class, 'icore'])->name('icore');
Route::get('/other-app', [EmployeeController::class, 'otherApp'])->name('other.app');
