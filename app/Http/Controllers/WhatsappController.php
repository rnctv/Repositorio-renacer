<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappController extends Controller
{
    // Token que Meta debe validar
    private $verify_token = '09Nov1985.Ell0nga';

    /**
     * WEBHOOK (GET = verificación, POST = mensajes entrantes)
     */
    public function webhook(Request $request)
    {
        Log::info('Webhook request received:', [
            'method' => $request->method(),
            'query'  => $request->query(),
            'body'   => $request->all()
        ]);

        /* =======================================================
         * 1) VERIFICACIÓN META (GET)
         * =======================================================
         */
        if ($request->isMethod('GET')) {

            $mode       = $request->input('hub_mode') 
                       ?? $request->input('hub.mode');

            $token      = $request->input('hub_verify_token') 
                       ?? $request->input('hub.verify_token');

            $challenge  = $request->input('hub_challenge') 
                        ?? $request->input('hub.challenge');

            Log::info("Meta verification incoming:", [
                'mode'      => $mode,
                'token'     => $token,
                'challenge' => $challenge,
            ]);

            if ($mode === 'subscribe' && $token === $this->verify_token) {
                return response($challenge, 200);
            }

            return response('Invalid token', 403);
        }

        /* =======================================================
         * 2) MENSAJES ENTRANTES (POST)
         * =======================================================
         */
        if ($request->isMethod('POST')) {

            Log::info("POST recibido desde WhatsApp:", $request->all());

            // Aquí puedes manejar mensajes entrantes
            // ejemplo:
            if (isset($request['entry'][0]['changes'][0]['value']['messages'][0])) {
                $msg = $request['entry'][0]['changes'][0]['value']['messages'][0];

                Log::info("Mensaje entrante detectado:", $msg);
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Método no permitido', 405);
    }

    /**
     * Vista simple de debug: muestra las últimas líneas del log
     */
    public function inbox()
    {
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            return "Log vacío.";
        }

        $content = file_get_contents($logPath);
        $lines   = explode("\n", $content);

        $lastLines = array_slice($lines, -200);

        return "<pre>" . implode("\n", $lastLines) . "</pre>";
    }
}
