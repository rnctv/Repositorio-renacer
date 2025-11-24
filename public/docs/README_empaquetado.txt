README — EMPAQUETADO COMPLETO (Uploader 3.2)

1) ¿Qué incluye el paquete completo?
   - Todo el proyecto con rutas tal cual están.
   - Dump de la base de datos (MySQL/SQLite/PostgreSQL).
   - Se excluyen por defecto: vendor/, node_modules/, storage/logs/, .git/, .idea/, tests/.
     (puedes incluir vendor/node_modules marcando las casillas en la UI).

2) ¿Dónde se guarda?
   - Carpeta externa definida por UPDATER_BASE (o ../updater_data por defecto).
   - Dentro de: <base>/backups/ con nombre project_full_YYYYMMDD_HHMMSS.zip

3) ¿Cómo generar el ZIP?
   - En la tarjeta “Backups & Paquetes” pulsa “Crear paquete”.
   - Opcional: marca “Incluir vendor/”, “Incluir node_modules/”, “Incluir storage/logs”.
   - Requiere tener el binario “zip” instalado (lo tienes en el servidor).

4) ¿Cómo restaurar?
   - Puedes descargar el ZIP desde el listado y descomprimirlo en otro entorno.
   - La BD vendrá como .sql o copia de .sqlite en /database_dump dentro del ZIP.

5) Recomendaciones:
   - Verifica permisos desde el botón “Probar permisos” en la UI.
   - Conserva varios ZIPs como puntos de restauración.
   - No compartas la URL con la key del updater.
