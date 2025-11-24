<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FinanceCategory;
use App\Models\FinanceSubcategory;

class FinanceCategoryController extends Controller
{
    public function index(Request $request)
    {
        $types = ['ingreso' => 'Ingresos', 'egreso' => 'Egresos'];

        $categories = FinanceCategory::orderBy('type')
            ->orderBy('name')
            ->get();

        $subcategories = FinanceSubcategory::orderBy('name')
            ->get()
            ->groupBy('category_id');

        return view('finanzas.categorias', [
            'types' => $types,
            'categories' => $categories,
            'subcategories' => $subcategories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:ingreso,egreso',
            'category_name' => 'required|string|max:255',
            'subcategory_name' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
        ]);

        $type = $data['type'];
        $categoryName = trim($data['category_name']);
        $subcategoryName = isset($data['subcategory_name']) ? trim($data['subcategory_name']) : null;
        $color = $data['color'] ?? null;

        $category = FinanceCategory::firstOrCreate(
            [
                'name' => $categoryName,
                'type' => $type,
            ],
            [
                'color' => $color ?: '#0f172a',
            ]
        );

        if ($color && $category->color !== $color) {
            $category->color = $color;
            $category->save();
        }

        if ($subcategoryName !== null && $subcategoryName !== '') {
            FinanceSubcategory::firstOrCreate(
                [
                    'category_id' => $category->id,
                    'name' => $subcategoryName,
                ]
            );
        }

        return redirect()
            ->route('finanzas.categorias.index')
            ->with('status', 'Categoría actualizada correctamente.');
    }
}
