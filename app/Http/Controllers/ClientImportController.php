<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
// Opción A (con Eloquent):
use App\Models\Cliente;
// Opción B (si no tienes modelo): usa DB
// use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function show(int $id): JsonResponse
    {
        try {
            // --- Opción A: usando modelo Eloquent ---
            $cliente = Cliente::findOrFail($id);

            // --- Opción B: si NO tienes modelo, descomenta estas 3 líneas y comenta la de arriba ---
            // $cliente = DB::table('clientes')->where('id', $id)->first();
            // if (!$cliente) return response()->json(['message' => 'Cliente no encontrado'], 404);

            return response()->json(['data' => $cliente]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        } catch (\Throwable $e) {
            \Log::error('Error show cliente', ['id' => $id, 'e' => $e->getMessage()]);
            return response()->json(['message' => 'No se pudo cargar la ficha'], 500);
        }
    }
}
