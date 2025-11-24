<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\ClienteImportController;
use App\Http\Controllers\ImportCategoriesController;
use App\Http\Controllers\AdminUpdaterController;
use App\Http\Controllers\WhatsappController;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| RUTAS WHATSAPP
|--------------------------------------------------------------------------
| webhook = recibe mensajes
| inbox   = ver mensajes recibidos desde el navegador (solo debug)
|--------------------------------------------------------------------------
*/

Route::get('/webhook/whatsapp', [WhatsappController::class, 'webhook']);
Route::post('/webhook/whatsapp', [WhatsappController::class, 'webhook']);

Route::get('/whatsapp/inbox', [WhatsappController::class, 'inbox']);   // 🔥 AHORA SÍ EXISTE


Route::get('/finanzas/categorias/import', [ImportCategoriesController::class, 'form'])->name('finanzas.import.form');
Route::post('/finanzas/categorias/import', [ImportCategoriesController::class, 'store'])->name('finanzas.import.store');

/*
|--------------------------------------------------------------------------
| Subdominio de Técnicos
|--------------------------------------------------------------------------
*/
Route::domain('tecnicos.renacerserv.xyz')->group(function () {
    Route::get('/', [AgendaController::class, 'tecnico'])->name('tecnico.home');
});

/*
|--------------------------------------------------------------------------
| Home (dominio principal)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Clientes (importación y consulta)
|--------------------------------------------------------------------------
*/
Route::get('/clientes',                 [ClienteImportController::class, 'index'])->name('clientes.index');
Route::get('/clientes/importar',        [ClienteImportController::class, 'form'])->name('clientes.form');
Route::post('/clientes/importar',       [ClienteImportController::class, 'upload'])->name('clientes.upload');

// Autocomplete de clientes (antes de /clientes/{cliente})
Route::get('/clientes/buscar',          [ClienteImportController::class, 'buscar'])->name('clientes.buscar');

// JSON de un cliente (ej: /clientes/1)
Route::get('/clientes/{cliente}',       [ClienteImportController::class, 'show'])
    ->whereNumber('cliente')
    ->name('clientes.show');

// Guardar/actualizar coordenadas del cliente
Route::patch('/clientes/{cliente}/coords', [ClienteImportController::class, 'updateCoords'])
    ->whereNumber('cliente')
    ->name('clientes.coords');

/*
|--------------------------------------------------------------------------
| Agenda (Kanban + Técnico)
|--------------------------------------------------------------------------
*/

// notificar
Route::post('/agenda/tareas/{tarea}/notificar', [AgendaController::class, 'notify'])
    ->whereNumber('tarea')
    ->name('agenda.notify');

// vista principal
Route::get('/agenda',                        [AgendaController::class, 'index'])->name('agenda.index');

// Kanban JSON
Route::get('/agenda/list',                   [AgendaController::class, 'list'])->name('agenda.list');

// Alias para FullCalendar
Route::get('/agenda/events',                 [AgendaController::class, 'events'])->name('agenda.events');

// crear
Route::post('/agenda',                       [AgendaController::class, 'store'])->name('agenda.store');

// mover
Route::patch('/agenda/tareas/{tarea}/mover', [AgendaController::class, 'move'])
    ->whereNumber('tarea')
    ->name('agenda.move');

// detalle
Route::get('/agenda/tareas/{tarea}',         [AgendaController::class, 'show'])
    ->whereNumber('tarea')
    ->name('agenda.show');

// contadores por día
Route::get('/agenda/counts',                 [AgendaController::class, 'counts'])->name('agenda.counts');

// *** NUEVO: pendientes (para el botón "Pendientes" en la vista) ***
Route::get('/agenda/pendientes',             [AgendaController::class, 'pendientes'])->name('agenda.pendientes');

// panel técnico también disponible en el dominio principal
Route::get('/tecnico',                       [AgendaController::class, 'tecnico'])->name('agenda.tecnico');

Route::get('/status', [\App\Http\Controllers\StatusController::class, 'index'])->name('status.index');

// guardar coordenadas de una tarea
Route::patch('/agenda/tareas/{tarea}/coords', [\App\Http\Controllers\AgendaController::class, 'coords'])
    ->whereNumber('tarea')
    ->name('agenda.coords');


    /*
|--------------------------------------------------------------------------
| Finanzas
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\FinanzasController;
use App\Http\Controllers\FinanceSalariesController;
Route::prefix('finanzas')->name('finanzas.')->group(function () {
    Route::post('/month-opening', [\App\Http\Controllers\FinanceMonthOpeningController::class, 'store'])->name('monthOpening.store');
    Route::post('/opening', [\App\Http\Controllers\FinanzasController::class, 'updateOpeningBalance'])->name('opening.store');
    Route::get('/', [FinanzasController::class, 'index'])->name('index');
    Route::get('/historial', [FinanzasController::class, 'historial'])->name('historial');
    Route::get('/historial/data', [FinanzasController::class, 'historialData'])->name('historial.data');
    Route::post('/', [FinanzasController::class, 'store'])->name('store');
    Route::delete('/{transaccion}', [FinanzasController::class, 'destroy'])->name('destroy');
    Route::get('/export.csv', [FinanzasController::class, 'exportCsv'])->name('export');
    Route::get('/sueldos', [FinanceSalariesController::class, 'index'])->name('salaries.index');
    Route::post('/sueldos', [FinanceSalariesController::class, 'store'])->name('salaries.store');

});


Route::middleware('updater.key')
    ->prefix('tools/updater')
    ->name('tools.updater.')
    ->group(function () {
        Route::get('/', [AdminUpdaterController::class, 'index'])->name('index');

        // Subidas (ZIP y archivo suelto por el método dispatcher upload())
        Route::post('/upload', [AdminUpdaterController::class, 'upload'])->name('upload');

        // Comandos (migrate, seeds, optimize, restore_latest, etc.)
        Route::post('/run', [AdminUpdaterController::class, 'run'])->name('run');

        // 👇 Ruta que te faltaba (para el botón "Descargar" en la vista)
        Route::get('/download/{file}', [AdminUpdaterController::class, 'downloadBackup'])->name('download');
        Route::post('/delete/{file}', [AdminUpdaterController::class, 'deleteBackup'])->name('delete');
    });
// WhatsApp Inbox Routes
require base_path('routes/whatsapp_routes.php');

