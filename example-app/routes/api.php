<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;

// Existing routes
Route::get('/logs', [LogController::class, 'index']);
Route::get('/logs/stats', [LogController::class, 'stats']);

// Developer Brokers
Route::get('/logs/developers', [LogController::class, 'indexDevelopers']);
Route::get('/logs/developers/{developer_id}', [LogController::class, 'showDeveloper']);
Route::get('/logs/developers/{developer_id}/stats', [LogController::class, 'developerStats']);

// Broker Management
Route::get('/logs/brokers', [LogController::class, 'indexBrokers']);
Route::get('/logs/brokers/{broker_id}', [LogController::class, 'showBroker']);
Route::get('/logs/brokers/{broker_id}/stats', [LogController::class, 'brokerStats']);
Route::get('/logs/brokers/{broker_id}/requests', [LogController::class, 'brokerRequests']);

// Traffic Analytics
Route::get('/logs/traffic/overview', [LogController::class, 'trafficOverview']);
Route::get('/logs/traffic/by-hour', [LogController::class, 'trafficByHour']);
Route::get('/logs/traffic/by-country', [LogController::class, 'trafficByCountry']);
Route::get('/logs/traffic/top-endpoints', [LogController::class, 'topEndpoints']);
Route::get('/logs/traffic/slow-requests', [LogController::class, 'slowRequests']);

// API Performance
Route::get('/logs/api/performance', [LogController::class, 'apiPerformance']);
Route::get('/logs/api/errors', [LogController::class, 'apiErrors']);
