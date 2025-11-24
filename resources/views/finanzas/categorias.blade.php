@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Categorías de finanzas</h1>
    <a href="{{ route('finanzas.index') }}" class="btn btn-sm btn-outline-secondary">
      Volver a Finanzas
    </a>
  </div>

  @if (session('status'))
    <div class="alert alert-success">
      {{ session('status') }}
    </div>
  @endif

  <div class="row">
    @foreach ($types as $typeKey => $typeLabel)
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header fw-bold">
            {{ $typeLabel }}
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0 align-middle">
                <thead>
                  <tr>
                    <th style="width: 35%;">Categoría</th>
                    <th>Subcategorías / Ítems</th>
                  </tr>
                </thead>
                <tbody>
                  @php
                    $rows = $categories->where('type', $typeKey);
                  @endphp

                  @forelse ($rows as $cat)
                    <tr>
                      <td>
                        <span class="fw-semibold">
                          {{ $cat->name }}
                        </span>
                      </td>
                      <td>
                        @php
                          $subs = $subcategories[$cat->id] ?? collect();
                        @endphp
                        @if ($subs->isEmpty())
                          <span class="text-muted small">Sin subcategorías</span>
                        @else
                          @foreach ($subs as $sub)
                            <span class="badge bg-light text-dark border me-1 mb-1">
                              {{ $sub->name }}
                            </span>
                          @endforeach
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="2" class="text-muted text-center py-3">
                        No hay categorías definidas para {{ strtolower($typeLabel) }}.
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card mt-3">
    <div class="card-header fw-bold">
      Agregar / actualizar categorías
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('finanzas.categorias.store') }}" class="row g-3">
        @csrf

        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <select name="type" class="form-select" required>
            <option value="ingreso" {{ old('type') === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
            <option value="egreso" {{ old('type','egreso') === 'egreso' ? 'selected' : '' }}>Egreso</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Categoría (padre)</label>
          <input type="text" name="category_name" class="form-control" value="{{ old('category_name') }}" required>
          <div class="form-text">
            Ej: Arriendo, Gastos comunes, Sueldos, Ventas fibra...
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Subcategoría / Ítem (hijo)</label>
          <input type="text" name="subcategory_name" class="form-control" value="{{ old('subcategory_name') }}">
          <div class="form-text">
            Opcional. Ej: Oficina Linares, Bodega, Técnico Juan...
          </div>
        </div>

        <div class="col-md-2">
          <label class="form-label">Color</label>
          <input type="color" name="color" class="form-control form-control-color" value="{{ old('color','#0f172a') }}">
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            Guardar
          </button>
        </div>

        @if ($errors->any())
          <div class="col-12">
            <div class="alert alert-danger mt-2">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        @endif
      </form>
    </div>
  </div>
</div>
@endsection
