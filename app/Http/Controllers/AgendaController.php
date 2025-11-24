<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use App\Models\TareaLog;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AgendaController extends Controller
{
    /**
     * Cargar asset-manifest.json
     */
    private function loadManifest()
    {
        $path = public_path('asset-manifest.json');
        if (!file_exists($path)) return [];
        $json = json_decode(file_get_contents($path), true);
        return is_array($json) ? $json : [];
    }

    /**
     * Vista principal del kanban
     */
    public function index(Request $request)
    {
        $selected = $request->query('fecha', Carbon::today()->toDateString());
        $assetManifest = $this->loadManifest();

        return view('agenda.kanban', [
            'selected'      => $selected,
            'gmapsKey'      => config('services.google.maps_browser_key') ?? env('GOOGLE_MAPS_BROWSER_KEY', ''),
            'recipients'    => $this->getDefaultRecipients(),
            'assetManifest' => $assetManifest,
        ]);
    }

    /**
     * Vista del técnico (usa mismo kanban)
     */
    public function tecnico(Request $request)
    {
        $selected = Carbon::today()->toDateString();
        $assetManifest = $this->loadManifest();

        return view('agenda.kanban', [
            'selected'      => $selected,
            'gmapsKey'      => config('services.google.maps_browser_key') ?? env('GOOGLE_MAPS_BROWSER_KEY', ''),
            'recipients'    => $this->getDefaultRecipients(),
            'assetManifest' => $assetManifest,
        ]);
    }

    /**
     * Listado por fecha (Kanban)
     */
    public function list(Request $request)
    {
        $fecha = $request->query('fecha', Carbon::today()->toDateString());

        $rows = Tarea::with('cliente')
            ->whereDate('fecha', $fecha)
            ->orderByRaw("FIELD(estado, 'pendiente','en_curso','completado')")
            ->orderBy('id', 'asc')
            ->get();

        $grouped = ['pendiente' => [], 'en_curso' => [], 'completado' => []];

        foreach ($rows as $t) {
            $grouped[$t->estado ?? 'pendiente'][] = $this->serializeTarea($t);
        }

        return response()->json(['ok' => true, 'data' => $grouped]);
    }

    /**
     * Eventos para calendario
     */
    public function events(Request $request)
    {
        $start = Carbon::parse($request->query('start', Carbon::today()->toDateString()))->startOfDay();
        $end   = Carbon::parse($request->query('end', Carbon::today()->addMonth()->toDateString()))->endOfDay();

        $rows = Tarea::with('cliente')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $events = $rows->map(function (Tarea $t) {
            $ser = $this->serializeTarea($t);
            return [
                'id'         => $t->id,
                'title'      => $t->titulo ?? 'Actividad',
                'start'      => optional($t->fecha)->toDateString(),
                'allDay'     => true,
                'classNames' => [
                    'estado-' . ($t->estado ?? 'pendiente'),
                    'tipo-' . Str::slug($t->tipo ?? 'otros')
                ],
                'extendedProps' => $ser,
            ];
        });

        return response()->json($events);
    }

    /**
     * Crear tarea
     */
    public function store(Request $r)
    {
        $v = Validator::make($r->all(), [
            'fecha'           => ['required', 'date'],
            'tipo'            => ['required', 'string', 'max:50'],
            'plan'            => ['nullable', 'string', 'max:120'],
            'titulo'          => ['nullable', 'string', 'max:200'],
            'descripcion'     => ['nullable', 'string'],
            'notas'           => ['nullable', 'string'],
            'cliente_id'      => ['nullable', 'integer', 'exists:clientes,id'],
            'cliente_nombre'  => ['nullable', 'string', 'max:200'],
            'cliente_tel'     => ['nullable', 'string', 'max:60'],
            'cliente_dir'     => ['nullable', 'string', 'max:255'],
            'lat'             => ['nullable', 'numeric'],
            'lng'             => ['nullable', 'numeric'],
            'cli_nombre'      => ['nullable', 'string', 'max:200'],
            'cli_telefono'    => ['nullable', 'string', 'max:60'],
            'cli_direccion'   => ['nullable', 'string', 'max:255'],
            'user_ppp_hotspot'=> ['nullable', 'string', 'max:120'],
            'precinto'        => ['nullable', 'string', 'max:60'],
            'tipo_otro'       => ['nullable', 'string', 'max:200'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $descripcion = $data['descripcion'] ?? $data['notas'] ?? null;

        // Normalización
        $data['cliente_nombre'] = $data['cliente_nombre'] ?? $r->input('cli_nombre');
        $data['cliente_tel']    = $data['cliente_tel']    ?? $r->input('cli_telefono');
        $data['cliente_dir']    = $data['cliente_dir']    ?? $r->input('cli_direccion');

        $tipo = Str::upper($data['tipo'] ?? '');
        if ($tipo === 'OTRO') $tipo = 'OTROS';
        $data['tipo'] = $tipo;

        // Si hay cliente, actualizar
        if (!empty($data['cliente_id'])) {
            if ($cli = Cliente::find($data['cliente_id'])) {
                if (!empty($data['cliente_nombre'])) $cli->nombre = Str::upper($data['cliente_nombre']);
                if (!empty($data['cliente_dir'])) $cli->direccion = Str::upper($data['cliente_dir']);
                if (!empty($data['cliente_tel'])) $cli->telefono = preg_replace('/\D+/', '', $data['cliente_tel']);
                $cli->save();

                if (empty($data['plan']) && !empty($cli->plan)) $data['plan'] = $cli->plan;
                if (empty($data['user_ppp_hotspot']) && !empty($cli->user_ppp_hotspot)) $data['user_ppp_hotspot'] = $cli->user_ppp_hotspot;
                if (empty($data['precinto']) && !empty($cli->precinto)) $data['precinto'] = $cli->precinto;
            }
        }

        $manual = $this->parseManualFields($descripcion ?? '');

        // Generar título si falta
        if (empty($data['titulo'])) {
            $nombre = null;
            if (!empty($data['cliente_id'])) {
                $nombre = optional(Cliente::find($data['cliente_id']))?->nombre;
            }
            if (!$nombre) {
                $nombre = $data['cliente_nombre'] ?? $manual['nombre'] ?? null;
                $nombre = $nombre ? Str::upper($nombre) : null;
            }
            $data['titulo'] = ucfirst(strtolower($data['tipo'])) . ($nombre ? ' - ' : '') . ($nombre ?? '');
        }

        $descripcionLimpia = $this->cleanDescription((string)($descripcion ?? ''));

        try {
            $tarea = Tarea::create([
                'fecha'             => $data['fecha'],
                'estado'            => 'pendiente',
                'tipo'              => $data['tipo'],
                'plan'              => isset($data['plan']) ? Str::upper($data['plan']) : null,
                'cliente_id'        => $data['cliente_id'] ?? null,
                'user_ppp_hotspot'  => $data['user_ppp_hotspot'] ?? null,
                'precinto'          => $data['precinto'] ?? null,
                'titulo'            => Str::upper($data['titulo']),
                'notas'             => $descripcionLimpia ?: null,

                'contacto_nombre'   => Str::upper($data['cliente_nombre'] ?? $manual['nombre'] ?? ''),
                'contacto_direccion'=> Str::upper($data['cliente_dir'] ?? $manual['direccion'] ?? ''),
                'contacto_telefono' => preg_replace('/\D+/', '', ($data['cliente_tel'] ?? $manual['telefono'] ?? '')),

                'coord_lat'         => $data['lat'] ?? null,
                'coord_lng'         => $data['lng'] ?? null,
            ]);

            TareaLog::create([
                'tarea_id'        => $tarea->id,
                'accion'          => 'created',
                'estado_anterior' => null,
                'estado_nuevo'    => 'pendiente',
                'fecha_anterior'  => null,
                'fecha_nueva'     => $tarea->fecha?->toDateString(),
                'user_id'         => optional($r->user())->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error creando tarea', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'id' => $tarea->id]);
    }

    /**
     * Mostrar tarea
     */
    public function show(Tarea $tarea)
    {
        $tarea->load('cliente');
        return response()->json([
            'ok'   => true,
            'data' => $this->serializeTarea($tarea),
            'recipients_defaults' => $this->getDefaultRecipients(),
        ]);
    }

    /**
     * Contadores para badges
     */
    public function counts(Request $request)
    {
        $fecha = $request->query('fecha', Carbon::today()->toDateString());
        $base = Tarea::whereDate('fecha', $fecha);

        return response()->json([
            'ok' => true,
            'data' => [
                'pendiente'  => (clone $base)->where('estado', 'pendiente')->count(),
                'en_curso'   => (clone $base)->where('estado', 'en_curso')->count(),
                'completado' => (clone $base)->where('estado', 'completado')->count(),
            ],
        ]);
    }

    /**
     * Mover tarea entre columnas
     */
    public function move(Request $r, Tarea $tarea)
    {
        $v = Validator::make($r->all(), [
            'estado' => ['required', 'in:pendiente,en_curso,completado'],
            'fecha'  => ['required', 'date'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $beforeEstado = $tarea->estado;
        $beforeFecha  = optional($tarea->fecha)->toDateString();

        $tarea->estado = $r->input('estado');
        $tarea->fecha  = $r->input('fecha');
        $tarea->save();

        TareaLog::create([
            'tarea_id'        => $tarea->id,
            'accion'          => 'moved',
            'estado_anterior' => $beforeEstado,
            'estado_nuevo'    => $tarea->estado,
            'fecha_anterior'  => $beforeFecha,
            'fecha_nueva'     => $tarea->fecha?->toDateString(),
            'user_id'         => optional($r->user())->id,
        ]);

        return response()->json([
            'ok'     => true,
            'fecha'  => $tarea->fecha?->toDateString(),
            'estado' => $tarea->estado,
        ]);
    }

    /**
     * Listado de tareas pendientes
     */
    public function pendientes(Request $request)
    {
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $from = $desde ? Carbon::parse($desde)->startOfDay() : Carbon::today();
        $to   = $hasta ? Carbon::parse($hasta)->endOfDay()   : Carbon::today()->addDays(14)->endOfDay();

        $rows = Tarea::with('cliente')
            ->where('estado', 'pendiente')
            ->whereBetween('fecha', [$from->toDateString(), $to->toDateString()])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return response()->json([
            'ok'   => true,
            'data' => $rows->map(fn($t) => $this->serializeTarea($t))->values(),
            'desde'=> $from->toDateString(),
            'hasta'=> $to->toDateString(),
        ]);
    }

    /**
     * Actualizar coordenadas desde modal Mapa
     */
    public function coords(Request $r, Tarea $tarea)
    {
        $v = Validator::make($r->all(), [
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok'=>false,'errors'=>$v->errors()], 422);
        }

        $beforeLat = $tarea->coord_lat;
        $beforeLng = $tarea->coord_lng;

        $tarea->coord_lat = $r->input('lat');
        $tarea->coord_lng = $r->input('lng');
        $tarea->save();

        TareaLog::create([
            'tarea_id' => $tarea->id,
            'accion'   => 'coords_updated',
            'user_id'  => optional($r->user())->id,
        ]);

        return response()->json([
            'ok'    => true,
            'data'  => $this->serializeTarea($tarea->fresh('cliente')),
            'before'=> ['lat'=>$beforeLat, 'lng'=>$beforeLng],
        ]);
    }

    /**
     * Notificar por AutoVIP
     */
    public function notify(Request $request, Tarea $tarea)
{
    $recipients = $request->input('recipients'); 
    $legacy     = $request->input('contact');    
    $message    = trim((string) $request->input('message', ''));

    if ($message === '') {
        $message = $this->buildPrettyMessage($tarea);
    }

    $numbers = [];

    if (is_array($recipients)) {
        foreach ($recipients as $r) {
            if (!empty($r['selected']) && !empty($r['number'])) {
                $numbers[] = trim((string)$r['number']);
            }
        }
    } elseif (is_array($legacy)) {
        foreach ($legacy as $n) {
            $n = trim((string)$n);
            if ($n !== '') $numbers[] = $n;
        }
    } else {
        foreach ($this->getDefaultRecipients() as $r) {
            if (!empty($r['selected']) && !empty($r['number'])) {
                $numbers[] = $r['number'];
            }
        }
    }

    if (empty($numbers)) {
        return response()->json([
            'ok'    => false,
            'error' => 'No hay destinatarios seleccionados.',
        ], 422);
    }

    $client = app(\App\Services\AutoVipClient::class);

    $result = $client->send($numbers, $message);
    $http   = (int) ($result['http'] ?? 0);
    $ok     = (bool) ($result['ok'] ?? false);

    if ($ok) {
        return response()->json([
            'ok'      => true,
            'sent'    => count($numbers),
            'logs'    => $result['logs'] ?? [],
            'body'    => $result['body'] ?? null,
            'message' => 'Notificaciones encoladas correctamente.',
        ], 200);
    }

    return response()->json([
        'ok'    => false,
        'error' => $result['message'] ?? ($result['error'] ?? 'Fallo al enviar'),
        'body'  => $result['body'] ?? null,
        'http'  => $http,
    ], $http > 0 ? $http : 502);
}

    /**
     * Parse descripción manual
     */
    private function parseManualFields(string $text): array
    {
        $s = (string)$text;
        $extract = function(string $labels) use ($s) {
            $re = '/(?:' . $labels . ')\\s*:\\s*([^\\n\\r]*)/iu';
            return preg_match($re, $s, $m) ? trim($m[1] ?? '') : null;
        };

        $nombre = $extract('Nombre|Cliente') ?: null;
        $telefono = $extract('Tel[eé]fono|Telefono|Tel') ?: null;
        $direccion = $extract('Direcci[oó]n|Direccion') ?: null;

        return [
            'nombre'    => $nombre,
            'telefono'  => $telefono,
            'direccion' => $direccion,
        ];
    }

    /**
     * Limpiar descripción
     */
    private function cleanDescription(string $notes): string
    {
        if ($notes === '') return '';
        $text = preg_replace('/^\s*(?:MOTIVO|NOMBRE|CLIENTE|TEL[ÉE]FONO|TELEFONO|TEL|DIRECCI[ÓO]N|DIRECCION|COORDENADAS|COORDS)\s*:\s*.*$/imu', '', $notes);
        $text = preg_replace("/(\\r?\\n){3,}/", "\n\n", $text);
        return trim($text);
    }

    /**
     * Serializar una tarea para el front
     */
    private function serializeTarea(Tarea $t): array
    {
        $raw = (string)($t->notas ?? '');
        $clean = $this->cleanDescription($raw);

        $cliente = $t->relationLoaded('cliente') ? $t->cliente : null;

        $nombre    = $t->contacto_nombre    ?: ($cliente->nombre ?? null);
        $telefono  = $t->contacto_telefono  ?: ($cliente->telefono ?? null);
        $direccion = $t->contacto_direccion ?: ($cliente->direccion ?? null);

        $clienteArr = [
            'id'        => $cliente->id ?? null,
            'nombre'    => $cliente->nombre ?? $nombre,
            'telefono'  => $cliente->telefono ?? $telefono,
            'direccion' => $cliente->direccion ?? $direccion,
        ];

        $mapsUrl = null;
        if ($t->coord_lat !== null && $t->coord_lng !== null) {
            $mapsUrl = 'https://www.google.com/maps?q=' . $t->coord_lat . ',' . $t->coord_lng;
        }

        return [
            'id'        => $t->id,
            'fecha'     => optional($t->fecha)->toDateString(),
            'estado'    => $t->estado,
            'tipo'      => $t->tipo,
            'plan'      => $t->plan,
            'user_ppp_hotspot' => $t->user_ppp_hotspot,
            'precinto'  => $t->precinto,
            'titulo'    => $t->titulo,
            'notas'     => $clean,

            'nombre'    => $nombre,
            'telefono'  => $telefono,
            'direccion' => $direccion,

            'coord_lat' => $t->coord_lat,
            'coord_lng' => $t->coord_lng,
            'maps_url'  => $mapsUrl,

            'cliente'   => $clienteArr,
            'contacto'  => [
                'nombre'    => $t->contacto_nombre,
                'telefono'  => $t->contacto_telefono,
                'direccion' => $t->contacto_direccion,
            ],
        ];
    }

    /**
     * Default de destinatarios
     */
    private function getDefaultRecipients(): array
    {
        return [
            ['name' => 'Harry',  'number' => '942415211', 'selected' => false, 'tags' => ['INSTALACION']],
            ['name' => 'Cesar',  'number' => '975897774', 'selected' => false, 'tags' => ['ASISTENCIA']],
            ['name' => 'Daniel', 'number' => '966368928', 'selected' => true,  'tags' => ['INSTALACION','ASISTENCIA']],
            ['name' => 'Juan',   'number' => '934603383', 'selected' => true,  'tags' => ['INSTALACION']],
            ['name' => 'Soporte 24/7', 'number' => '900000000', 'selected' => false, 'tags' => ['ASISTENCIA']],
        ];
    }
}
