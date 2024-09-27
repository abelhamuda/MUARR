<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SSOController;
use App\Http\Controllers\HomeController;


Route::get('/', [HomeController::class, 'index'])->name('home');
Route::match(['get', 'post'], '/sso-process', [SSOController::class, 'ssoprocess'])->name('sso.process');
Route::get('/alphabit', [EmployeeController::class, 'alphabit'])->name('alphabit');
Route::get('/icore', [EmployeeController::class, 'icore'])->name('icore');
Route::get('/other-app', [EmployeeController::class, 'otherApp'])->name('other.app');
