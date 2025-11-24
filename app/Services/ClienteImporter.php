<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use League\Csv\Reader;

class ClienteImporter
{
    public function import(string $path, string $originalName, string $mode = 'mikrowisp'): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv','xlsx','xls'])) throw new \RuntimeException('Formato no soportado: '.$ext);

        [$headers, $rows] = $ext === 'csv' ? $this->readCsv($path) : $this->readXlsx($path, $mode);

        // Si 1ª columna es vacía en todo el archivo, elimínala
        if (isset($headers[0]) && trim((string)$headers[0])==='') {
            $allBlank = true;
            foreach ($rows as $r) { if (!empty(trim((string)($r[0] ?? '')))) { $allBlank=false; break; } }
            if ($allBlank) { array_shift($headers); foreach ($rows as &$r) { array_shift($r); } }
        }

        // Asegurar esquema
        $this->ensureSchema();

        // Mapeo EXACTO: encabezado del archivo -> columna DB
        // (sin mezclar, sin alias; solo variantes con/sin acentos donde aplica)
        $map = [
            'Id'                    => 'id_externo',
            'Nombre'                => 'nombre',
            'Dirección Principal'   => 'direccion',
            'Direccion Principal'   => 'direccion',
            'Dia pago'              => 'dia_pago',
            'Correo'                => 'correo',
            'Telefono'              => 'telefono',
            'Teléfono'              => 'telefono',
            'Plan'                  => 'plan',
            'Movil'                 => 'movil',
            'Móvil'                 => 'movil',
            'Instalado'             => 'instalado',
            'Cedula'                => 'cedula',
            'Cédula'                => 'cedula',
            'User PPP/Hotspot'      => 'user_ppp_hotspot',
            'Coordenadas'           => 'coordenadas',
            'Status'                => 'status',
            'Precinto'              => 'precinto',
            'Valor plan'            => 'valor_plan',
            'El plan'               => 'el_plan',
            'Fecha Pago'            => 'fecha_pago',
        ];

        // Índices de headers que nos interesan
        $idxWanted = [];
        foreach ($headers as $i => $h) {
            $h = trim((string)$h);
            if (isset($map[$h])) $idxWanted[$i] = $map[$h];
        }

        $inserted=0; $updated=0; $skipped=0; $batch=[];
        foreach ($rows as $row) {
            // Requerimos Id (id_externo) para el upsert
            $id = null;
            foreach ($idxWanted as $i=>$col) {
                if ($col==='id_externo') {
                    $id = isset($row[$i]) ? trim((string)$row[$i]) : null;
                    break;
                }
            }
            if (!$id) { $skipped++; continue; }

            $payload = ['id_externo'=>$id];
            foreach ($idxWanted as $i=>$col) {
                if ($col==='id_externo') continue;
                $v = isset($row[$i]) ? trim((string)$row[$i]) : null;
                $payload[$col] = ($v==='') ? null : $v;
            }

            // Derivar 'estado' desde final de 'nombre' si viene el tag (ACTIVO/SUSPENDIDO/RETIRADO)
            if (!empty($payload['nombre'])) {
                [$estado,$nom] = $this->extractEstado($payload['nombre']);
                if ($estado) $payload['estado'] = $estado;
                if ($nom)    $payload['nombre'] = $nom;
            }

            $now = now()->toDateTimeString();
            $payload['updated_at'] = $now;
            if (!isset($payload['created_at'])) $payload['created_at'] = $now;

            $batch[] = $payload;
            if (count($batch) >= 1000) { [$a,$b] = $this->upsert($batch); $inserted+=$a; $updated+=$b; $batch=[]; }
        }
        if (!empty($batch)) { [$a,$b] = $this->upsert($batch); $inserted+=$a; $updated+=$b; }

        return ['insertados'=>$inserted,'actualizados'=>$updated,'sin_id'=>$skipped];
    }

    protected function extractEstado(string $nombre): array
    {
        $pat='/(?:\\s*[-–—:]?\\s*|\\s*\\(|\\s*\\[)?\\s*(ACTIVO|SUSPENDIDO|RETIRADO)\\s*(?:\\)|\\])?\\s*$/i';
        $estado=''; $clean=$nombre;
        if (preg_match($pat,$nombre,$m)){
            $estado=strtoupper($m[1]);
            $clean=preg_replace($pat,'',$nombre);
            $clean=preg_replace('/[\\s\\-–—:]+$/','',$clean);
            $clean=trim($clean);
        }
        return [$estado,$clean];
    }

    protected function upsert(array $batch): array
    {
        $all = array_keys($batch[0]);
        $updateCols = array_values(array_diff($all, ['id_externo','created_at']));
        DB::table('clientes')->upsert($batch, ['id_externo'], $updateCols);
        // estimación simple
        $ids = array_map(fn($r)=>$r['id_externo'], $batch);
        $existing = DB::table('clientes')->whereIn('id_externo',$ids)->count();
        $ins = max(0,count($batch)-$existing); $upd = min($existing,count($batch));
        return [$ins,$upd];
    }

    protected function readCsv(string $path): array
    {
        $csv = Reader::createFromPath($path, 'r'); $csv->skipInputBOM();
        $rows = iterator_to_array($csv->getRecords());
        $rows = array_map(fn($r)=>array_values($r), $rows);
        if (empty($rows)) return [[],[]];
        $headers = $rows[0]; $data = array_slice($rows,1);
        return [$headers,$data];
    }

    protected function readXlsx(string $path, string $mode): array
    {
        $reader = ReaderEntityFactory::createXLSXReader(); $reader->open($path);
        $headers=[]; $rows=[]; $i=0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells=[]; foreach ($row->getCells() as $cell) { $cells[] = trim((string)$cell->getValue()); }
                $i++;
                if ($mode==='mikrowisp') {
                    if ($i===1) continue;              // Fila 1: título
                    if ($i===2) { $headers=$cells; continue; } // Fila 2: encabezados
                    $rows[]=$cells;
                } else {
                    if ($i===1) { $headers=$cells; continue; }
                    $rows[]=$cells;
                }
            } break;
        }
        $reader->close();
        return [$headers,$rows];
    }

    protected function ensureSchema(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            if (!Schema::hasColumn('clientes','id_externo')) {
                $t->string('id_externo')->nullable()->after('id');
                try { $t->unique('id_externo','clientes_id_externo_unique'); } catch (\Throwable $e) {}
            }
            $cols = [
                'nombre','direccion','dia_pago','correo','telefono','plan','movil','instalado',
                'cedula','user_ppp_hotspot','coordenadas','status','precinto','valor_plan','el_plan','fecha_pago'
            ];
            foreach ($cols as $c) if (!Schema::hasColumn('clientes',$c)) $t->string($c)->nullable();
            if (!Schema::hasColumn('clientes','estado')) {
                try { $t->enum('estado',['ACTIVO','SUSPENDIDO','RETIRADO'])->nullable()->index(); }
                catch (\Throwable $e) { $t->string('estado')->nullable()->index(); }
            }
        });
    }
}
