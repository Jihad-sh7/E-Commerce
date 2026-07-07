<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Jobs\DailySalesBatchJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('register',[UserController::class,'register']);
Route::post('login',[UserController::class,'login']);

Route::middleware('auth:sanctum')->group(function(){

Route::post('logout',[UserController::class,'logout']);

Route::get('products',[ProductController::class,'index']);
Route::get('/products/{id}', [ProductController::class, 'showProduct']); 
Route::post('products', [ProductController::class, 'storeProduct']);
Route::put('/products/{id}', [ProductController::class, 'updateProduct']);
Route::delete('/products/{id}', [ProductController::class, 'destroyProduct']);


Route::post('/purchase/pessimistic', [OrderController::class, 'purchasePessimistic']);
Route::post('/purchase/optimistic', [OrderController::class, 'purchaseOptimistic']);

Route::get('/reports/sales', [OrderController::class, 'generateSalesReport']);
Route::get('/reports/download/{date}', [OrderController::class, 'downloadSalesReport']); 
});


Route::any('/load-balancer/{any}', function (Request $request, $any) {
    
    $servers = [
        'http://127.0.0.1:8001',
        'http://127.0.0.1:8002',
        'http://127.0.0.1:8003'
    ];

    $pointer = Cache::get('load_balancer_pointer', 0);
    $selectedServer = $servers[$pointer];

    $nextPointer = ($pointer + 1) % count($servers);
    Cache::put('load_balancer_pointer', $nextPointer);

    $destinationUrl = $selectedServer . '/api/' . $any;
    $method = strtolower($request->method()); 

    echo "\n  [Load Balancer]: Forwarding [" . strtoupper($method) . "] to Node: " . $selectedServer . " -> Path: /api/" . $any . "\n";

    try {
        $response = Http::withHeaders([
            'Authorization' => $request->header('Authorization'), 
            'Accept' => 'application/json'
        ])->$method($destinationUrl, $request->all());

        return response()->json([
            'balancer_info' => 'Request handled dynamically by Node: ' . $selectedServer,
            'server_response' => $response->json()
        ], $response->status());

    } catch (\Exception $e) {
        return response()->json(['error' => 'السيرفر المختار غير متاح حالياً!'], 502);
    }
})->where('any', '.*');