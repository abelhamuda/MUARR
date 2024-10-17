<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SSOController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AlphabitController;
use App\Http\Controllers\IcoreController;
use App\Http\Controllers\RiskAppController;


Route::get('/', [HomeController::class, 'index'])->name('home');
Route::match(['get', 'post'], '/sso-process', [SSOController::class, 'ssoprocess'])->name('sso.process');
Route::match(['get', 'post'], '/alphabit', [AlphabitController::class, 'alphabit'])->name('alphabit');
Route::match(['get', 'post'], '/icore', [IcoreController::class, 'icore'])->name('icore');
Route::match(['get', 'post'], '/risk_app', [RiskAppController::class, 'risk_app'])->name('risk_app');
Route::get('/icore', [IcoreController::class, 'icore'])->name('icore');
Route::get('/other-app', [EmployeeController::class, 'otherApp'])->name('other.app');
