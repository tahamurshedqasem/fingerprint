<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContractController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// تأكد من أن هذا المسار موجود
Route::prefix('contracts')->group(function () {
    Route::get('/', [ContractController::class, 'index']);
    Route::post('/', [ContractController::class, 'store']);
    Route::get('/{id}', [ContractController::class, 'show']);
    Route::post('/{id}/sign', [ContractController::class, 'sign']);
    Route::post('/{id}/save', [ContractController::class, 'save']);
    Route::post('/{id}/upload-screenshot', [ContractController::class, 'uploadScreenshot']);
    Route::get('/verify/{id}', [ContractController::class, 'verify']);
});

// اختبار بسيط للتأكد من أن API يعمل
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});