@extends('layouts.app')
@section('title','Clientes')

@php
  // Orden por defecto id_externo ASC
  $curSort = request('sort','id_externo');
  $curDir  = request('dir','asc');
@endphp

@section('content')
<div class="card">
  <div class="card-body">
    <form method="get" class="toolbar" action="{{ route('clientes.index') }}">
      <div class="input-wrap">
        <input class="input" type="text" name="s" id="filtro-s"
               value="{{ request('s') }}" placeholder="Filtrar..." autocomplete="off" />
        <button type="button" class="clear-btn" id="btn-clear-s" aria-label="Limpiar">✕</button>
      </div>

      <select name="estado" class="select">
        <option value="">Estado: Todos</option>
        @foreach(['ACTIVO','SUSPENDIDO','RETIRADO'] as $st)
          <option value="{{ $st }}" @selected(request('estado')===$st)>{{ $st }}</option>
        @endforeach
      </select>

      <input type="hidden" name="sort" value="{{ $curSort }}" />
      <input type="hidden" name="dir"  value="{{ $curDir }}" />

      <button class="btn" type="button" onclick="location.href='{{ route('clientes.index') }}'">Reset</button>
      <button class="btn primary">Buscar</button>
      <a href="{{ route('clientes.form') }}" class="btn">Importar</a>
    </form>

    {{-- 🔹 Zona que se reemplaza por AJAX --}}
    <div id="clientes-area">
      @include('clientes.partials.table', ['clientes'=>$clientes])
    </div>
  </div>
</div>

{{-- JS de la página (AJAX + modal) --}}
<script src="{{ asset('js/clientes.js') }}?v={{ $assetManifest['js/clientes.js'] ?? '' }}"></script>
@endsection
