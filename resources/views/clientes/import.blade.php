<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Importar clientes</title>
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;} .box{max-width:960px;margin:2rem auto;padding:1rem;border:1px solid #ddd;border-radius:12px;background:#fff}</style>
</head>
<body>
  <div class="box">
    <h1>Importar clientes (MikroWISP)</h1>
    @if (session('ok'))
      <div style="margin:.5rem 0;padding:.5rem 1rem;border-radius:8px;background:#e6ffed;color:#03543f;">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
      <div style="margin:.5rem 0;padding:.5rem 1rem;border-radius:8px;background:#ffe6e6;color:#7d0000;">
        <ul style="margin:0;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <p>Acepta <strong>CSV</strong> o <strong>XLSX</strong>. Para Excel de MikroWISP: la <em>fila 1 es título</em>, la <em>fila 2 contiene encabezados</em>. La primera columna en blanco se ignora. En <em>Nombre</em> se detecta el estado (ACTIVO/SUSPENDIDO/RETIRADO) y se guarda en la columna <strong>estado</strong>.</p>

    <form method="post" action="{{ route('clientes.upload') }}" enctype="multipart/form-data" style="margin-top:1rem;display:flex;gap:.5rem;align-items:center;">
      @csrf
      <input type="file" name="archivo" required />
      <button style="padding:.6rem 1rem;border:0;border-radius:8px;background:#111;color:#fff;cursor:pointer;">Subir e importar</button>
      <a href="{{ route('clientes.index') }}" style="margin-left:auto;">Ver clientes</a>
    </form>

    @if(session('map'))
      <details style="margin-top:1rem;"><summary>Ver mapeo de encabezados</summary>
        <pre style="white-space:pre-wrap;background:#f6f8fa;padding:1rem;border-radius:8px;">{{ json_encode(session('map'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
      </details>
    @endif
  </div>
</body>
</html>
