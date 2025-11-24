<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>@yield('title','Panel')</title>

  {{-- CSRF para peticiones AJAX --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">
  {{-- MANIFIESTO DE ASSETS VERSIONADOS (JS/CSS) --}}
  @php
    $assetManifest = [];
    $manifestPath = public_path('asset-manifest.json');
    if (file_exists($manifestPath)) {
        $json = file_get_contents($manifestPath);
        $assetManifest = json_decode($json, true) ?? [];
    }
  @endphp



  {{-- GOOGLE MAPS KEY (inyectada desde config/services.php -> services.google.maps_browser_key) --}}
  @php($gmapsKey = config('services.google.maps_browser_key'))
  @if(!empty($gmapsKey))
    <meta name="google-maps-key" content="{{ $gmapsKey }}">
  @endif

  <meta name="theme-color" content="#1f242b" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#1f242b" media="(prefers-color-scheme: dark)">

  <style>
    /* --- base --- */
    *,*::before,*::after{ box-sizing:border-box }
    html,body{ height:100% }
    body{ margin:0; overflow-x:hidden; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial; }

    :root{
      --brand-bg:#1f242b; --brand-fg:#e6edf3;
      --bg:#f6f7fb; --text:#0f172a; --muted:#6b7280;
      --card:#ffffff; --border:#e5e7eb; --accent:#2563eb;
    }
    body{ background:var(--bg); color:var(--text) }

    /* HEADER */
    .app-header{ position:sticky; top:0; z-index:100; background:var(--brand-bg); color:var(--brand-fg); border-bottom:1px solid rgba(255,255,255,.08) }
    .wrap{ width:100%; padding:.65rem 12px; display:flex; align-items:center; gap:1rem }
    .brand img{ height:36px; display:block }
    .nav{ margin-left:auto; display:flex; gap:.25rem; flex-wrap:wrap }
    .nav a{ color:var(--brand-fg); text-decoration:none; padding:.45rem .7rem; border-radius:.6rem; opacity:.9 }
    .nav a:hover{ opacity:1; background:rgba(255,255,255,.10) }
    .nav a.active{ background:rgba(255,255,255,.18); opacity:1 }

    /* CONTENEDOR */
    .container{ width:100%; max-width:none; margin:1.25rem auto; padding:0 12px }

    /* CARD/TABLA */
    .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 6px 20px rgba(10,10,20,.05) }
    .card .card-body{ padding:1rem }
    .toolbar{ display:flex; gap:.5rem; align-items:center; margin:0 0 1rem 0; flex-wrap:wrap }
    .input{ flex:1; min-width:220px; padding:.55rem .8rem; border:1px solid var(--border); border-radius:10px; background:#fff }
    .select{ padding:.55rem .8rem; border:1px solid var(--border); border-radius:10px; background:#fff }
    .btn{ padding:.55rem .9rem; border:1px solid var(--border); border-radius:10px; background:#fff; cursor:pointer }
    .btn.primary{ background:var(--accent); color:#fff; border-color:var(--accent) }
    .table-wrap{ overflow:auto; border:1px solid var(--border); border-radius:12px }
    table{ border-collapse:collapse; width:100%; font-size:.95rem }
    thead th{ position:sticky; top:0; background:#fff; border-bottom:1px solid var(--border); padding:.7rem; text-align:left; font-size:.8rem; color:var(--muted); letter-spacing:.3px }
    tbody td{ padding:.7rem; border-bottom:1px solid var(--border) }
    tbody tr:nth-child(even){ background:#fafafa }
    tbody tr:hover{ background:#f2f6ff }
    a.link{ color:#2563eb; text-decoration:none }
    a.link:hover{ text-decoration:underline }
    .th a{ color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:.25rem }
    .arrow{ font-size:.75rem; opacity:.65 }

    /* Paginación */
    .pagination{ display:flex; gap:.25rem; list-style:none; padding:0; margin:1rem 0; flex-wrap:wrap }
    .pagination a,.pagination span{
      display:inline-flex; align-items:center; justify-content:center;
      min-width:32px; height:32px; padding:0 .5rem;
      border:1px solid var(--border); border-radius:.5rem;
      text-decoration:none; color:#334155; background:#fff; font-size:.9rem; line-height:1;
    }
    .pagination .active span{ background:var(--accent); border-color:var(--accent); color:#fff }
    .pagination a:hover{ background:#f3f4f6 }
    .pagination .chev{ font-size:1rem; line-height:1 }

    @media (max-width:640px){
      table, thead, tbody, th, td, tr{ display:block }
      thead{ display:none }
      tr{ border-bottom:1px solid var(--border) }
      td{ display:flex; justify-content:space-between; gap:.5rem }
      td::before{ content:attr(data-label); font-weight:600; color:var(--muted) }
    }

    /* ===== Modal de Ficha de Cliente ===== */
    .modal-backdrop{ position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center; padding:12px; z-index:5000 }
    .modal-backdrop.show{ display:flex }
    .modal-card{ width:min(840px,96vw); background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 20px 50px rgba(0,0,0,.25); overflow:hidden }
    .modal-head{ display:flex; align-items:center; justify-content:space-between; padding:.9rem 1rem; border-bottom:1px solid var(--border) }
    .modal-title{ font-weight:700 }
    .modal-close{ appearance:none; border:0; background:transparent; font-size:1.35rem; line-height:1; cursor:pointer }
    .modal-body{ padding:1rem }
    .grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap:.75rem }
    .item{ padding:.6rem .75rem; border:1px solid var(--border); border-radius:10px; background:#fff }
    .item small{ display:block; color:var(--muted); margin-bottom:.25rem }
    .spinner{ display:none; padding:1rem; text-align:center; color:#555 }
    .spinner.show{ display:block }

    /* ===== Botón ✕ dentro del buscador ===== */
    .input-wrap{ position:relative; display:flex; align-items:center; }
    .input-wrap .input{ padding-right:2rem; }
    .clear-btn{
      position:absolute; right:.5rem;
      background:transparent; border:0; cursor:pointer;
      font-size:1.1rem; line-height:1; padding:.15rem .25rem;
      color:var(--muted); display:none;
    }
    .clear-btn:hover{ color:#111827 }
  </style>

  @stack('head')
</head>
<body>
  <header class="app-header">
    <div class="wrap">
      <a class="brand" href="{{ route('clientes.index') }}">
        <img
          src="{{ secure_asset('logoN.png') }}?v={{ file_exists(public_path('logoN.png')) ? filemtime(public_path('logoN.png')) : time() }}"
          alt="Renacer"
        />
      </a>
      <nav class="nav">
        <a href="{{ route('clientes.index') }}" class="{{ request()->routeIs('clientes.index') ? 'active' : '' }}">Clientes</a>
        <a href="{{ route('clientes.form')  }}" class="{{ request()->routeIs('clientes.form')  ? 'active' : '' }}">Importar</a>
        {{-- Enlace a la Agenda (fallback si no existe la ruta nombrada) --}}
        @if(\Illuminate\Support\Facades\Route::has('agenda.index'))
          <a href="{{ route('agenda.index')  }}" class="{{ request()->routeIs('agenda.*')  ? 'active' : '' }}">Agenda</a>
        @else
          <a href="{{ url('/agenda') }}" class="{{ request()->is('agenda*') ? 'active' : '' }}">Agenda</a>
        @endif

        {{-- Finanzas --}}
        @if(\Illuminate\Support\Facades\Route::has('finanzas.index'))
        <a href="{{ route('finanzas.index') }}" class="{{ request()->routeIs('finanzas.*') ? 'active' : '' }}">Finanzas</a>
        @endif

      </nav>
    </div>
  </header>

  <main class="container">
    @yield('content')
  </main>

  {{-- GOOGLE MAPS KEY para JS (alias GMAPS_KEY + GOOGLE_MAPS_KEY) --}}
  <script>
    window.GMAPS_KEY = @json(config('services.google.maps_browser_key'));
    window.GOOGLE_MAPS_KEY = window.GMAPS_KEY;
  </script>

  @stack('scripts')
</body>
</html>
