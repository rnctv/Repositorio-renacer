<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\ClienteImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClienteImportController extends Controller
{
    public function index(Request $r)
    {
        $q = Cliente::query();

        // Filtros
        if ($estado = $r->string('estado')->toString()) {
            $q->where('estado', $estado);
        }

        if ($s = $r->string('s')->toString()) {
            $cols = Schema::getColumnListing('clientes');
            $q->where(function ($qq) use ($cols, $s) {
                foreach ($cols as $c) {
                    if (in_array($c, ['id','created_at','updated_at'])) continue;
                    $qq->orWhere($c, 'like', "%{$s}%");
                }
            });
        }

        // Orden por defecto: id_externo asc (numérico)
        $sort = $r->get('sort', 'id_externo');
        $dir  = strtolower($r->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'id_externo') {
            $q->orderByRaw('CAST(id_externo AS UNSIGNED) ' . $dir)
              ->orderBy('id_externo', $dir);
        } elseif ($sort && Schema::hasColumn('clientes', $sort)) {
            $q->orderBy($sort, $dir);
        } else {
            $q->orderByRaw('CAST(id_externo AS UNSIGNED) ASC')->orderBy('id_externo', 'asc');
        }

        $clientes = $q->paginate(25)->withQueryString();

        // Parcial para AJAX (tabla)
        if ($r->boolean('partial')) {
            return view('clientes.partials.table', compact('clientes'));
        }

        return view('clientes.index', compact('clientes'));
    }

    public function form()
    {
        return view('clientes.import');
    }

    public function upload(Request $r, ClienteImporter $importer)
    {
        $r->validate(['archivo' => ['required','file','mimes:csv,txt,xlsx,xls']]);

        $file   = $r->file('archivo');
        $stored = $file->storeAs('imports', time().'_'.preg_replace('/\s+/', '_', $file->getClientOriginalName()));
        $path   = storage_path('app/'.$stored);

        $res = $importer->import($path, $file->getClientOriginalName(), 'mikrowisp');

        return back()->with('ok', 'Importado: '.$res['insertados'].' nuevos, '.$res['actualizados'].' actualizados, '.$res['sin_id'].' sin ID');
    }

    // JSON para el modal de ficha de cliente
    public function show(Cliente $cliente)
    {
        $data = $cliente->toArray();
        unset($data['id']);

        if (!empty($cliente->extras)) {
            $extra = json_decode($cliente->extras, true);
            if (is_array($extra)) {
                foreach ($extra as $k => $v) {
                    if (!array_key_exists($k, $data)) $data[$k] = $v;
                }
            }
        }

        $norm = function (string $k) {
            $k = iconv('UTF-8','ASCII//TRANSLIT', $k);
            $k = strtolower(preg_replace('/[^a-z0-9]+/','_', $k));
            return trim($k, '_');
        };

        $hide = [
            'ip_receptor','tipo_estrato','caja_nap','plan_voip','mac','proximo_pago','saldo',
            'emisor','router','pasarela','user_ubnt','total_cobrar','status',
            'servicios_personalizados','fecha_retirado'
        ];
        $hideSet = array_flip($hide);

        $filtered = [];
        foreach ($data as $k => $v) {
            $nk = $norm($k);
            if (isset($hideSet[$nk])) continue;
            if (str_starts_with($nk, 'col_sin_nombre')) continue;
            $filtered[$k] = $v;
        }

        return response()->json(['ok' => true, 'data' => $filtered]);
    }

    // ====== NUEVO: Autocomplete de clientes ======
    public function buscar(Request $r)
    {
        $s = trim((string)$r->query('s', ''));
        if ($s === '') {
            return response()->json(['ok' => true, 'data' => []]);
        }

        // Campos más útiles para buscar
        $cols = ['id_externo','nombre','direccion','telefono','movil','correo'];

        $q = Cliente::query();
        $q->where(function ($qq) use ($s, $cols) {
            foreach ($cols as $c) {
                $qq->orWhere($c, 'like', "%{$s}%");
            }
        });

        $res = $q->orderBy('nombre')
                 ->limit(20)
                 ->get([
                     'id',
                     'id_externo',
                     'nombre',
                     'direccion',
                     'telefono',
                     'movil',
                     'plan',
                     'el_plan',
                     'valor_plan',
                     'user_ppp_hotspot',
                     'precinto',
                     'coordenadas',
                 ]);

        return response()->json(['ok' => true, 'data' => $res]);
    }

    public function updateCoords(Request $r, \App\Models\Cliente $cliente)
{
    $data = $r->validate([
        'lat' => ['required','numeric','between:-90,90'],
        'lng' => ['required','numeric','between:-180,180'],
    ]);

    $lat = number_format((float)$data['lat'], 6, '.', '');
    $lng = number_format((float)$data['lng'], 6, '.', '');

    $cliente->coordenadas = $lat.','.$lng;
    $cliente->save();

    return response()->json(['ok'=>true, 'coordenadas'=>$cliente->coordenadas]);
}

}
