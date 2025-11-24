# Uploader 3.0 (ZIP + rollback + logs + permisos + archivo suelto)

## Archivos incluidos
- `app/Http/Controllers/AdminUpdaterController.php`
- `resources/views/tools/updater.blade.php`

## Rutas (agregar en routes/web.php)
```php
use App\Http\Controllers\AdminUpdaterController;

Route::middleware('updater.key')
    ->prefix('tools/updater')
    ->name('tools.updater.')
    ->group(function () {
        Route::get('/', [AdminUpdaterController::class, 'index'])->name('index');
        Route::post('/upload-zip', [AdminUpdaterController::class, 'uploadZip'])->name('upload.zip');
        Route::post('/upload-file', [AdminUpdaterController::class, 'uploadSingle'])->name('upload.file');
        Route::post('/run', [AdminUpdaterController::class, 'run'])->name('run');
        Route::get('/download/{file}', [AdminUpdaterController::class, 'downloadBackup'])->name('download');
    });
```

## Variables de entorno
- `UPDATER_KEY` — clave para acceder (query `?key=` o header `X-Updater-Key`).
- `UPDATER_BASE` — (opcional) ruta absoluta para almacenar **fuera del proyecto** los `uploads/`, `backups/` y `logs/`.
  - Si NO la defines, usa por defecto `../updater_data` (carpeta hermana al proyecto).

## Requisitos
- PHP ZipArchive habilitado.
- Permisos de escritura en el proyecto y en el almacenamiento externo.
