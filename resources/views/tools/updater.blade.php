@extends('layouts.app')

@section('content')
<style>
:root {
  --up-bg: #050b16;
  --up-bg-alt: #0f1723;
  --up-panel: #111b2a;
  --up-border: #223047;
  --up-ink: #e2e8ff;
  --up-muted: #8aa2c3;
  --up-pill: #2a8bd0;
  --up-accent: #3dd68c;
}
body { background: radial-gradient(circle at top, #1b2a3f 0, #050b16 55%, #02040a 100%); color: var(--up-ink); }

.up-shell { max-width: 1240px; }

.card {
  background: rgba(11, 18, 32, 0.96);
  border-radius: 14px;
  border: 1px solid rgba(54, 83, 121, 0.7);
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.6);
}

.card-header {
  border-bottom: 1px solid rgba(34, 48, 71, 0.9);
}

.brand-pill {
  background: linear-gradient(135deg, #30a3ff, #2af5ff);
  color: #050816;
  width: 38px;
  height: 38px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  box-shadow: 0 0 0 1px rgba(9, 16, 35, 0.9), 0 10px 25px rgba(0,0,0,0.7);
}

h1.h4 {
  font-weight: 600;
  letter-spacing: 0.03em;
}

h1 .ver { color: var(--up-accent); }

.tiny { font-size: 11px; }
.muted { color: var(--up-muted); }

.btn-soft, .btn-up, .btn-up-soft {
  border-radius: 999px;
  border-width: 1px;
  border-style: solid;
  border-color: rgba(80, 122, 180, 0.9);
  background: radial-gradient(circle at top left, #1f3554 0, #101827 55%, #0a101b 100%);
  color: #e4eeff;
  padding-inline: 14px;
  padding-block: 6px;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-soft:hover,
.btn-up:hover,
.btn-up-soft:hover {
  background: radial-gradient(circle at top left, #254062 0, #141f33 55%, #050910 100%);
  color: #ffffff;
}

.btn-up {
  background: linear-gradient(135deg, #2a8bd0, #3dd68c);
  border-color: transparent;
  color: #020617;
  font-weight: 600;
}
.btn-up:hover {
  filter: brightness(1.05);
  color: #020617;
}

.btn-up-soft {
  background: rgba(20, 35, 60, 0.95);
}

code {
  background: rgba(5, 11, 24, 0.9);
  color: #9ad2ff;
  padding: 2px 6px;
  border-radius: 999px;
  font-size: 11px;
}

.up-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 18px;
}
.up-brand {
  display: flex;
  align-items: center;
  gap: 14px;
}
.up-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.up-badge {
  border-radius: 999px;
  border: 1px solid rgba(79, 119, 185, 0.8);
  padding: 3px 10px;
  font-size: 11px;
  color: var(--up-muted);
  backdrop-filter: blur(8px);
  background: rgba(10, 21, 41, 0.8);
}

/* Tabs */
.up-tabs .nav-link {
  font-size: 13px;
  padding: 7px 14px;
  color: var(--up-muted);
  border-radius: 999px 999px 0 0;
  border-bottom: none;
}
.up-tabs .nav-link.active {
  background: rgba(11, 18, 32, 0.97);
  color: #f9fbff;
  border-color: var(--up-border);
}

/* Forms & layout */
.form-label { font-size: 12px; color: var(--up-muted); }
.form-control, .form-select {
  background: #020617;
  border-radius: 10px;
  border: 1px solid rgba(55, 76, 110, 0.9);
  color: #e2edff;
  font-size: 13px;
}
.form-control:focus, .form-select:focus {
  box-shadow: 0 0 0 1px #2a8bd0;
  border-color: #2a8bd0;
}

hr { border-color: rgba(34, 48, 71, 0.8); }

/* Artisan buttons */
.up-artisan form { margin-bottom: 0; }
.up-artisan .btn-soft {
  font-size: 12px;
  padding-inline: 10px;
  padding-block: 5px;
}

/* Backups list */
.up-backups {
  list-style: none;
  padding-left: 0;
  margin-bottom: 0;
}
.up-backups li {
  border-bottom: 1px solid rgba(25, 39, 64, 0.8);
  padding-block: 6px;
}
.up-backups li:last-child {
  border-bottom: none;
}
.up-backups-code {
  font-size: 12px;
}
.up-backups-meta {
  font-size: 11px;
  color: var(--up-muted);
}

/* Responsive */
@media (max-width: 768px) {
  .up-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .up-badges {
    justify-content: flex-start;
  }
}
</style>

<div class="container-xxl py-4 up-shell">
  <header class="up-header">
    <div class="up-brand">
      <div class="brand-pill">UP</div>
      <div>
        <h1 class="h4 m-0">Uploader <span class="ver">3.3</span></h1>
        <div class="tiny muted">
          Empaquetado con <code>zip -r</code> • <b>api/</b> y <b>brdge/</b> se omiten por defecto • Dump de BD automático
        </div>
      </div>
    </div>
    <div class="up-badges">
      <div class="up-badge">PHP {{ PHP_VERSION }}</div>
      <div class="up-badge">Laravel {{ app()->version() }}</div>
      <a class="btn-soft" href="{{ url()->current() }}?key={{ request('key') }}">↻ Refrescar</a>
    </div>
  </header>

  @if ($errors->any())
    <div class="alert alert-danger mb-2">
      <ul class="mb-0 tiny">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('status'))
    @php $st = session('status'); @endphp
    @if (is_array($st))
      <div class="alert alert-{{ $st['type'] ?? 'info' }} mb-2 tiny">{{ $st['message'] ?? '' }}</div>
    @else
      <div class="alert alert-success mb-2 tiny">{{ $st }}</div>
    @endif
  @endif

  @if (session('output'))
    <div class="card mb-3">
      <div class="card-header tiny">Salida de comando</div>
      <pre class="m-0 p-3 tiny">{{ session('output') }}</pre>
    </div>
  @endif

  @if (session('copied'))
    <div class="card mb-3">
      <div class="card-header tiny">Archivos actualizados ({{ count(session('copied')) }})</div>
      <div class="card-body">
        <ul class="small mb-0 tiny">
          @foreach (session('copied') as $f)
            <li><code>{{ $f }}</code></li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  @if (session('import_errors'))
    <div class="alert alert-warning mb-3 tiny">
      <div class="fw-bold mb-1">Archivos no instalados:</div>
      <ul class="mb-0">
        @foreach (session('import_errors') as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <ul class="nav nav-tabs up-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-package" type="button" role="tab">Paquete completo</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zip" type="button" role="tab">Actualizar ZIP</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-file" type="button" role="tab">Subir archivo</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-artisan" type="button" role="tab">Comandos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-backup" type="button" role="tab">Backups</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-perms" type="button" role="tab">Permisos</button></li>
  <li class="nav-item">
    <a class="nav-link" href="/whatsapp/inbox?key={{ request('key') }}">WhatsApp Inbox</a>
</li>
</ul>

  <div class="tab-content">

    {{-- Paquete completo --}}
    <div class="tab-pane fade show active" id="tab-package" role="tabpanel">
      <div class="card mb-3">
        <div class="card-header tiny">Crear paquete completo</div>
        <div class="card-body">
          <form method="POST" action="{{ route('tools.updater.run') }}" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="key" value="{{ request('key') }}">
            <input type="hidden" name="cmd" value="package_full">
            <div class="col-12">
              <div class="tiny muted mb-1">
                Dump de BD incluido automáticamente si la herramienta está disponible.
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-check tiny">
                <input class="form-check-input" type="checkbox" id="upIncludeVendor" name="include_vendor">
                <label class="form-check-label" for="upIncludeVendor">Incluir <code>vendor/</code></label>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-check tiny">
                <input class="form-check-input" type="checkbox" id="upIncludeNode" name="include_node_modules">
                <label class="form-check-label" for="upIncludeNode">Incluir <code>node_modules/</code></label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-check tiny">
                <input class="form-check-input" type="checkbox" id="upIncludeLogs" name="include_storage_logs">
                <label class="form-check-label" for="upIncludeLogs">Incluir <code>storage/logs</code></label>
              </div>
            </div>

            <div class="col-12"><hr class="my-2"></div>

            <div class="col-md-3">
              <div class="form-check tiny">
                <input class="form-check-input" type="checkbox" id="upIncludeApi" name="include_api">
                <label class="form-check-label" for="upIncludeApi">Incluir <code>api/</code> (omitido por defecto)</label>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-check tiny">
                <input class="form-check-input" type="checkbox" id="upIncludeBridge" name="include_bridge">
                <label class="form-check-label" for="upIncludeBridge">Incluir <code>brdge/</code> (omitido por defecto)</label>
              </div>
            </div>
            <div class="col-md-2 ms-auto">
              <button class="btn-up w-100">📦 Crear paquete</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Subir ZIP --}}
    <div class="tab-pane fade" id="tab-zip" role="tabpanel">
      <div class="card mb-3">
        <div class="card-header tiny">Subir paquete ZIP</div>
        <div class="card-body">
          <form method="POST" action="{{ route('tools.updater.upload') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="key" value="{{ request('key') }}">
            <div class="col-md-8">
              <label class="form-label">Archivo ZIP</label>
              <input type="file" name="package" class="form-control" required>
              <div class="tiny muted mt-1">
                El ZIP debe respetar rutas del proyecto <code>app/</code>, <code>resources/</code>, <code>database/</code>, etc.
              </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn-up w-100">Instalar ZIP</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Subir archivo individual --}}
    <div class="tab-pane fade" id="tab-file" role="tabpanel">
      <div class="card mb-3">
        <div class="card-header tiny">Subir archivo individual</div>
        <div class="card-body">
          <form method="POST" action="{{ route('tools.updater.upload') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="key" value="{{ request('key') }}">
            <div class="col-md-4">
              <label class="form-label">Archivo</label>
              <input type="file" name="file" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Destino (ruta relativa en el proyecto)</label>
              <input type="text" name="dest" class="form-control" placeholder="ej: app/Http/Controllers/MiControlador.php" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button class="btn-up-soft w-100">Subir y reemplazar</button>
            </div>
          </form>
          <div class="tiny muted mt-2">
            Se hace backup del destino si existe antes de reemplazarlo.
          </div>
        </div>
      </div>
    </div>

    {{-- Comandos --}}
    <div class="tab-pane fade" id="tab-artisan" role="tabpanel">
      <div class="card mb-3 up-artisan">
        <div class="card-header tiny">Atajos de Artisan</div>
        <div class="card-body">
          <form method="POST" action="{{ route('tools.updater.run') }}" class="d-flex flex-wrap gap-2">
            @csrf
            <input type="hidden" name="key" value="{{ request('key') }}">
            <button class="btn-soft" name="cmd" value="migrate" type="submit">php artisan migrate</button>
            <button class="btn-soft" name="cmd" value="seed_payment_methods" type="submit">Seed: PaymentMethodsSeeder</button>
            <button class="btn-soft" name="cmd" value="seed_november_opening" type="submit">Seed: NovemberOpeningSeeder</button>
            <button class="btn-soft" name="cmd" value="optimize_clear" type="submit">optimize:clear</button>
            <button class="btn-soft" name="cmd" value="cache_clear" type="submit">cache:clear</button>
            <button class="btn-soft" name="cmd" value="config_cache" type="submit">config:cache</button>
            <button class="btn-soft" name="cmd" value="route_cache" type="submit">route:cache</button>
          </form>
        </div>
      </div>
    </div>

    {{-- Backups --}}
    <div class="tab-pane fade" id="tab-backup" role="tabpanel">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center tiny">
          <span>Backups disponibles</span>
          <form method="POST" action="{{ route('tools.updater.run') }}" class="m-0">
            @csrf
            <input type="hidden" name="key" value="{{ request('key') }}">
            <input type="hidden" name="cmd" value="restore_latest">
            <button class="btn-up-soft btn-sm">Restaurar último backup</button>
          </form>
        </div>
        <div class="card-body">
          @php
            $customBase = env('UPDATER_BASE');
            $storeBase = $customBase ? rtrim($customBase, '/') : rtrim(realpath(base_path('..')) ?: base_path('..'), '/').'/updater_data';
            $backupDir = $storeBase . '/backups';
          @endphp
          @if (!empty($backups))
            <ul class="up-backups">
              @foreach ($backups as $b)
                @php
                  $name = is_array($b) ? ($b['name'] ?? reset($b)) : $b;
                  $fullPath = $backupDir . '/' . $name;
                  $size = is_file($fullPath) ? filesize($fullPath) : null;
                  $time = is_file($fullPath) ? filemtime($fullPath) : null;
                @endphp
                <li class="d-flex align-items-center justify-content-between gap-3">
                  <div class="small">
                    <div class="up-backups-code"><code>{{ $name }}</code></div>
                    <div class="up-backups-meta">
                      @if ($size)
                        {{ number_format($size / 1024 / 1024, 2) }} MB
                      @endif
                      @if ($size && $time)
                        •
                      @endif
                      @if ($time)
                        {{ \Carbon\Carbon::createFromTimestamp($time)->format('Y-m-d H:i:s') }}
                      @endif
                    </div>
                  </div>
                  <div class="d-flex gap-2">
                    @if (Route::has('tools.updater.download'))
                      <a class="btn-soft btn-sm" href="{{ route('tools.updater.download', ['file'=>$name, 'key'=>request('key')]) }}">Descargar</a>
                    @endif
                    @if (Route::has('tools.updater.delete'))
                      <form method="POST" action="{{ route('tools.updater.delete', $name) }}" onsubmit="return confirm('¿Eliminar backup {{ $name }}?');">
                        @csrf
                        <input type="hidden" name="key" value="{{ request('key') }}">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                      </form>
                    @endif
                  </div>
                </li>
              @endforeach
            </ul>
          @else
            <div class="muted tiny">Aún no hay backups.</div>
          @endif
        </div>
      </div>
    </div>

    {{-- Permisos --}}
    <div class="tab-pane fade" id="tab-perms" role="tabpanel">
      <div class="card mb-3">
        <div class="card-header tiny">Permisos & paths externos</div>
        <div class="card-body">
          <p class="muted tiny mb-2">
            El almacenamiento externo queda <b>fuera del proyecto</b>. Por defecto se usa: <code>../updater_data</code>.
            Podés cambiarlo con <code>UPDATER_BASE</code> en el <code>.env</code>.
          </p>
          <form method="POST" action="{{ route('tools.updater.run') }}">
            @csrf
            <input type="hidden" name="key" value="{{ request('key') }}">
            <button class="btn-up-soft" name="cmd" value="check_perms" type="submit">Volver a probar permisos</button>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
