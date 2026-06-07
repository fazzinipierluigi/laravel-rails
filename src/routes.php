<?php

use Illuminate\Support\Facades\Route;
use Fazzinipierluigi\LaravelRails\Http\Controllers\EditorController;
use Fazzinipierluigi\LaravelRails\Http\Controllers\ExecutionController;
use Fazzinipierluigi\LaravelRails\Http\Controllers\FormController;
use Fazzinipierluigi\LaravelRails\Http\Controllers\TriggerController;
use Illuminate\Http\Response;

Route::prefix('laravel-rails')
     ->middleware(['web'])
     ->name('laravel-rails.')
     ->group(function () {
         // Visual editor API
         Route::get('api/workflow/{slug}', [EditorController::class, 'show'])
              ->name('workflow.show');

         Route::put('api/workflow/{slug}', [EditorController::class, 'update'])
              ->name('workflow.update');

         Route::get('api/registered-actions', [EditorController::class, 'registeredActions'])
              ->name('registered-actions');

         Route::get('api/csrf', fn() => response()->json(['token' => csrf_token()]))
              ->name('csrf');

         // Form execution
         Route::post('transition/{transitionId}/execute', [FormController::class, 'execute'])
              ->name('transition.execute');

         // Manual trigger
         Route::post('trigger/{triggerId}/fire', [TriggerController::class, 'fire'])
              ->name('trigger.fire');

         // Execution viewer
         Route::get('api/execution/{instanceId}', [ExecutionController::class, 'data'])
              ->name('execution.data');

         Route::get('api/execution/{instanceId}/{type}/{subjectId}', [ExecutionController::class, 'nodeLogs'])
              ->name('execution.node-logs')
              ->where(['type' => 'state|transition', 'subjectId' => '[0-9a-f\-]+']);

         // Static package assets (whitelist only known safe files)
         Route::get('assets/{file}', function (string $file) {
             $allowed = ['jsonlogic_ui.js', 'jsonlogic_ui.css'];
             if (!in_array($file, $allowed, true)) {
                 abort(404);
             }
             $path = realpath(__DIR__ . '/../resources/assets/' . $file);
             if (!$path || !file_exists($path)) {
                 abort(404);
             }
             $mime = str_ends_with($file, '.js') ? 'application/javascript' : 'text/css';
             return new Response(file_get_contents($path), 200, [
                 'Content-Type'  => $mime,
                 'Cache-Control' => 'public, max-age=86400',
             ]);
         })->name('assets')->where('file', '[a-zA-Z0-9_\-\.]+');
     });
