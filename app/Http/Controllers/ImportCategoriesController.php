<?php declare(strict_types=1);
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{FinanceCategory, FinanceSubcategory};

class ImportCategoriesController extends Controller
{
    public function form()
    {
        return view('finanzas.import_categories');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (!$handle) {
            return back()->withErrors(['file'=>'No se pudo leer el archivo'])->withInput();
        }

        // Se espera encabezado: tipo,categoria,subcategoria,color
        $header = fgetcsv($handle);
        $norm = function($v){ return strtolower(trim((string)$v)); };
        $index = [];
        foreach ($header ?? [] as $i => $h) {
            $index[$norm($h)] = $i;
        }
        $need = ['tipo','categoria','subcategoria','color'];
        foreach ($need as $n) { if (!array_key_exists($n,$index)) { $index[$n] = -1; } }

        $createdCats = 0; $createdSubs = 0; $rows = 1; $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows++;
            $tipo = $index['tipo']>=0 ? $norm($row[$index['tipo']] ?? '') : '';
            $categoria = $index['categoria']>=0 ? trim((string)($row[$index['categoria']] ?? '')) : '';
            $subcategoria = $index['subcategoria']>=0 ? trim((string)($row[$index['subcategoria']] ?? '')) : '';
            $color = $index['color']>=0 ? trim((string)($row[$index['color']] ?? '')) : '#999999';

            if ($tipo === '') { $errors[] = "Fila $rows: 'tipo' vacío."; continue; }
            if (!in_array($tipo, ['ingreso','egreso','gasto'])) { $errors[] = "Fila $rows: 'tipo' inválido ($tipo)."; continue; }
            if ($tipo === 'gasto') $tipo = 'egreso';
            if ($categoria === '') { $errors[] = "Fila $rows: 'categoria' vacía."; continue; }

            // Crear/actualizar categoría con ese tipo
            $cat = FinanceCategory::firstOrCreate(['name'=>$categoria], ['type'=>$tipo, 'color'=>$color]);
            // Si ya existía pero tiene otro tipo, lo sincronizamos al que manda el CSV
            if ($cat->type !== $tipo) {
                $cat->type = $tipo;
                $cat->save();
            }
            if (!empty($color) && $cat->color !== $color) {
                $cat->color = $color;
                $cat->save();
            }
            if ($cat->wasRecentlyCreated) $createdCats++;

            // Subcategoría (opcional)
            if ($subcategoria !== '') {
                $sub = FinanceSubcategory::firstOrCreate(['category_id'=>$cat->id,'name'=>$subcategoria]);
                if ($sub->wasRecentlyCreated) $createdSubs++;
            }
        }
        fclose($handle);

        $msg = "Importación OK. Categorías nuevas: $createdCats. Subcategorías nuevas: $createdSubs.";
        if (!empty($errors)) {
            return back()->with('status', $msg)->with('import_errors', $errors);
        }
        return back()->with('status', $msg);
    }
}
