<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoVipClient
{
    private string $url;
    private string $key;
    private ?string $instance;
    private ?string $sender;
    private int $timeout;
    private bool $forceIpv4;
    private bool $tlsInsecure;

    public function __construct()
    {
        $cfg = config('services.autovip', []);

        $this->url       = (string)($cfg['url'] ?? '');
        $this->key       = (string)($cfg['key'] ?? '');
        $this->instance  = $cfg['instance'] ?? null; // opcional
        $this->sender    = $cfg['sender'] ?? null;   // opcional

        // Networking / seguridad
        $this->timeout     = (int)($cfg['timeout'] ?? (int) env('AUTOVIP_TIMEOUT', 20));
        $this->forceIpv4   = filter_var($cfg['force_ipv4'] ?? env('AUTOVIP_FORCE_IPV4', true), FILTER_VALIDATE_BOOLEAN);
        $this->tlsInsecure = filter_var($cfg['tls_insecure'] ?? env('AUTOVIP_TLS_INSECURE', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Envía mensaje(s) vía BIG.
     * $numbers: array de strings (+569..., 569..., 9........)
     */
    public function send(array $numbers, string $message): array
    {
        // 0) Config
        if (!$this->url || !$this->key) {
            $err = ['ok' => false, 'message' => 'AUTOVIP sin configurar', 'endpoint' => $this->url];
            Log::warning('AUTOVIP config missing', $err);
            return $err;
        }

        // 1) Normalizar CL a E.164 SIN '+'
        $normalized = [];
        foreach ($numbers as $n) {
            $e164 = $this->normalizePhoneCL((string) $n);
            if ($e164) $normalized[] = $e164;
        }
        $normalized = array_values(array_unique($normalized));

        if (!$normalized) {
            return ['ok' => false, 'message' => 'Formato de contacto inválido', 'endpoint' => $this->url];
        }

        // 2) Payload BIG (con '+') y verify FALSE para entregar (no solo validar)
        $contacts = array_map(function ($d) use ($message) {
            $withPlus = str_starts_with($d, '+') ? $d : ('+' . $d);
            return ['message' => $message, 'number' => $withPlus];
        }, $normalized);

        $payload = [
            'contact' => $contacts,
            'verify'  => false, // <- ENTREGA real; 'true' solo valida
        ];

        if ($this->instance) $payload['instance'] = $this->instance;
        if ($this->sender)   $payload['sender']   = $this->sender;

        // 3) HTTP client
        $http = Http::acceptJson()
            ->timeout($this->timeout)
            ->retry(0, 0);

        if ($this->tlsInsecure) $http = $http->withOptions(['verify' => false]);
        if ($this->forceIpv4)   $http = $http->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]);

        try {
            $res  = $http->withHeaders(['Api-key' => $this->key])->asJson()->post($this->url, $payload);
            $code = $res->status();
            $body = $res->json() ?? $res->body();

            // Log de diagnóstico siempre, para trazabilidad
            Log::info('AUTOVIP BIG response', [
                'status'     => $code,
                'body'       => $body,
                'endpoint'   => $this->url,
                'normalized' => $normalized,
                'verify'     => false,
                'instance'   => $this->instance,
                'sender'     => $this->sender,
            ]);

            if ($res->successful() || in_array($code, [200, 201], true)) {
                return [
                    'ok'         => true,
                    'status'     => $code,
                    'body'       => $body,
                    'endpoint'   => $this->url,
                    'normalized' => $normalized,
                    'verify'     => false,
                ];
            }

            Log::warning('AUTOVIP send failed', [
                'status'     => $code,
                'body'       => $body,
                'url'        => $this->url,
                'normalized' => $normalized,
            ]);

            return [
                'ok'         => false,
                'status'     => $code,
                'body'       => $body,
                'endpoint'   => $this->url,
                'normalized' => $normalized,
            ];
        } catch (\Throwable $e) {
            Log::warning('AUTOVIP exception', [
                'error'      => $e->getMessage(),
                'url'        => $this->url,
                'normalized' => $normalized,
            ]);

            return [
                'ok'         => false,
                'message'    => 'Excepción al enviar (AUTOVIP BIG)',
                'error'      => $e->getMessage(),
                'endpoint'   => $this->url,
                'normalized' => $normalized,
            ];
        }
    }

    /**
     * Normaliza teléfonos CL a E.164 (sin '+'): 569XXXXXXXX
     * Acepta: '+569xxxxxxxx', '569xxxxxxxx', '9xxxxxxxx'
     */
    private function normalizePhoneCL(string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', $raw ?? '');
        if ($d === '') return null;
        if (str_starts_with($d, '56')) {
            return strlen($d) === 11 ? $d : null;
        }
        if ($d[0] === '0') return null;
        if (strlen($d) === 9 && $d[0] === '9') return '56' . $d;
        return null;
    }
}
