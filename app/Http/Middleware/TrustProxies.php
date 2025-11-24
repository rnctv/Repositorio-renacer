<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Confía en todos los proxies (Nginx/Cloudflare/ELB, etc.).
     * Si quieres, aquí puedes poner un array con IPs específicas.
     */
    protected $proxies = '*';

    /**
     * Encabezados a considerar para detectar esquema/host reales.
     * Usa SOLO una de estas dos opciones:
     *   - Con proxies genéricos:
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    //   - Si usas AWS ELB/ALB, comenta lo de arriba y usa SOLO esta línea:
    // protected $headers = Request::HEADER_X_FORWARDED_AWS_ELB;
}
