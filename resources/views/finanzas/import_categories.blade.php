@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-3">Importar categorías y subcategorías</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  @if (session('import_errors'))
    <div class="alert alert-warning">
      <div class="fw-bold mb-1">Advertencias:</div>
      <ul class="mb-0">
      @foreach (session('import_errors') as $e)
        <li>{{ $e }}</li>
      @endforeach
      </ul>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-body">
      <p class="mb-2">Descargá la <a href="/template_categorias.csv" target="_blank">plantilla CSV</a> y edítala en Excel/Google Sheets.</p>
      <ul class="small">
        <li>Encabezados requeridos: <code>tipo,categoria,subcategoria,color</code> (podés dejar <code>subcategoria</code> y <code>color</code> vacíos).</li>
        <li><b>tipo</b>: <code>ingreso</code> o <code>egreso</code> (si pones <code>gasto</code> lo convierto a <code>egreso</code>).</li>
        <li>Una fila por subcategoría. Si querés solo la categoría, dejá <i>subcategoria</i> vacío.</li>
        <li>Guardá como <b>CSV (UTF‑8)</b>.</li>
      </ul>
      <form method="POST" action="{{ route('finanzas.import.store') }}" enctype="multipart/form-data" class="row g-2">
        @csrf
        <div class="col-auto">
          <input class="form-control" type="file" name="file" accept=".csv" required>
        </div>
        <div class="col-auto">
          <button class="btn btn-primary">Importar</button>
          <a class="btn btn-outline-secondary" href="{{ route('finanzas.index') }}">Volver a Finanzas</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Ejemplos</div>
    <div class="card-body">
<pre class="mb-0"><code>tipo,categoria,subcategoria,color
egreso,ARRIENDO,CASA,#4b5563
egreso,ARRIENDO,OFICINA,#4b5563
egreso,GASTOS COMUNES,LUZ CASA,#a855f7
egreso,GASTOS COMUNES,LUZ OFICINA,#a855f7
egreso,GASTOS COMUNES,AGUA CASA,#a855f7
egreso,SISTEMAS,CLIENTES,#2563eb
egreso,SISTEMAS,MENSAJES,#2563eb
ingreso,VENTAS,EFECTIVO,#16a34a
ingreso,VENTAS,TARJETA,#16a34a</code></pre>
    </div>
  </div>
</div>
@endsection
