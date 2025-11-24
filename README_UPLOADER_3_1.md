# Uploader 3.1 (dry-run, conflicto, restaurar específico, historial)

## Archivos
- app/Http/Controllers/AdminUpdaterController.php
- resources/views/tools/updater.blade.php

## Rutas que usa (compatibles con tu app actual)
- tools.updater.index
- tools.updater.upload   → POST /tools/updater/upload   (ZIP y archivo suelto)
- tools.updater.run      → POST /tools/updater/run
- tools.updater.download → GET  /tools/updater/download/{file}

## Novedades
- Dry-run (simula sin copiar) y política de conflicto (replace/skip).
- Backup auto antes de instalar. Restaurar último o uno seleccionado.
- Historial con detalle por instalación (install_*.json) y log de auditoría.
- Upload de archivo individual (con backup del destino si existe).

## Almacenamiento externo
- Por defecto en ../updater_data (fuera del proyecto). Cambia con UPDATER_BASE en .env.
- Estructura: uploads/, backups/, logs/.
