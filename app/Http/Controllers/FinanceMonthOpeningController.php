<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FinanceMonthOpening;

class FinanceMonthOpeningController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'year' => ['required','integer','min:2000','max:2100'],
            'month'=> ['required','integer','min:1','max:12'],
            'payment_method_id' => ['nullable','integer'],
            'amount' => ['required','numeric','min:0'],
            'source' => ['nullable','in:manual,carryover'],
            'edit_pass' => ['required','string'],
        ]);

        $pass = trim((string)($data['edit_pass'] ?? ''));
        $expected = trim((string)(config('app.fin_opening_pass') ?? env('FIN_OPENING_PASS', '09Nov1985.Ell0nga')));
        if (!hash_equals($expected, $pass)) {
            return back()->withInput()->with('open_modal','opening')->withErrors(['edit_pass'=>'Contraseña incorrecta.']);
        }

        $data['source'] = $data['source'] ?? 'manual';

        FinanceMonthOpening::updateOrCreate(
            [
                'year' => (int)$data['year'],
                'month'=> (int)$data['month'],
                'payment_method_id' => $data['payment_method_id'] ? (int)$data['payment_method_id'] : null,
            ],
            [
                'amount' => (float)$data['amount'],
                'source' => $data['source'],
            ]
        );

        return redirect()->route('finanzas.index', ['period' => sprintf('%04d-%02d', $data['year'], $data['month'])])
            ->with('status','Saldo inicial guardado');
    }
}
