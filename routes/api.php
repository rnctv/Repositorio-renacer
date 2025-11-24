<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteImportController; // <- usamos el que SÍ existe

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
| API pública para consultar un cliente por ID.
| Reutilizamos el mismo método "show" de ClienteImportController,
| y mantenemos el nombre del parámetro {cliente} para que funcione
| el Route Model Binding si tu método lo usa.
*/
Route::get('/clientes/{cliente}', [ClienteImportController::class, 'show'])
    ->whereNumber('cliente')
    ->name('api.clientes.show');
