FINANZAS - AUTOPATCH (Saldo inicial)

Qué corrige
- Card verde: muestra 'Saldo inicial' real desde BD.
- Card azul:
  * Suma el saldo inicial al número grande (saldo general).
  * Suma al 'Saldo cuenta' la parte del saldo inicial registrada para 'CUENTA EMPRESA' (o medio con 'CUENTA').

Archivos incluidos
- app/Models/FinanceMonthOpening.php
- app/Providers/AppServiceProvider.php

Instalación
1) Sube y extrae este ZIP en la raíz del proyecto (reemplaza archivos).
2) Ejecuta en consola:
   php artisan config:clear
   php artisan cache:clear
   php artisan optimize:clear
   php artisan view:clear
3) Recarga el navegador con Ctrl+F5.

Notas
- Requiere que exista la tabla 'finance_month_openings' con columnas: year, month, amount, payment_method_id (nullable), source.
- Debe existir un medio 'CUENTA EMPRESA' o con 'CUENTA' en el nombre para que el ajuste impacte 'Saldo cuenta'.
