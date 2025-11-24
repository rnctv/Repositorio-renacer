<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUpdaterKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->query('key')
            ?? $request->header('X-Updater-Key')
            ?? $request->input('key')   // <- importante para los POST
            ?? '';

        // La clave debe estar definida en config/app.php como 'updater_key' => env('UPDATER_KEY', '')
        $expected = (string) config('app.updater_key', '');

        if ($expected === '') {
            abort(403, 'Acceso denegado (UPDATER_KEY no configurada en config/app.php)');
        }

        if (!hash_equals($expected, (string) $key)) {
            abort(403, 'Acceso denegado');
        }

        return $next($request);
    }
}
