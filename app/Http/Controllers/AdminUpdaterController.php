<?php declare(strict_types=1);
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUpdaterController extends Controller
{
    public function index(Request $request)
    {
        [$storeBase, $dirs] = $this->ensureStorageBase();
        $backups    = $this->listBackups($dirs['backups']);
        $logPreview = $this->readLogTail($dirs['logs'].'/updater.log', 2000);

        return view('tools.updater', [
            'backups'    => $backups,
            'logPreview' => $logPreview,
        ]);
    }

    public function upload(Request $request)
    {
        if ($request->hasFile('package')) return $this->uploadZip($request);
        if ($request->hasFile('file') && $request->filled('dest')) return $this->uploadSingle($request);
        return back()->withErrors(['package' => 'No se encontró archivo para subir.']);
    }

    public function uploadZip(Request $request)
    {
        $request->validate(['package' => ['required','file','mimes:zip']]);
        [$storeBase, $dirs] = $this->ensureStorageBase();

        if (!is_dir($dirs['uploads'])) @mkdir($dirs['uploads'], 0775, true);
        if (!is_writable($dirs['uploads'])) {
            return back()->withErrors(['package'=>"No se puede escribir en {$dirs['uploads']}"]);
        }

        $tmpZip = $dirs['uploads'].'/pkg_'.date('Ymd_His').'.zip';
        $request->file('package')->move($dirs['uploads'], basename($tmpZip));

        $extractTo = $dirs['uploads'].'/extract_'.uniqid();
        @mkdir($extractTo, 0775, true);

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            return back()->withErrors(['package'=>'No se pudo abrir el ZIP'])->withInput();
        }
        $zip->extractTo($extractTo);
        $zip->close();

        $base = base_path();
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractTo, \FilesystemIterator::SKIP_DOTS));
        $toBackup = [];
        foreach ($it as $item) {
            if (!$item->isFile()) continue;
            $rel  = trim(str_replace($extractTo, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            $dest = $base.DIRECTORY_SEPARATOR.$rel;
            if (is_file($dest)) $toBackup[$rel] = $dest;
        }

        $backupName = 'backup_'.date('Ymd_His').'.zip';
        $backupPath = $dirs['backups'].'/'.$backupName;
        $this->createBackupZip($backupPath, $toBackup);

        $copied = []; $failed = [];
        $it2 = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractTo, \FilesystemIterator::SKIP_DOTS));
        foreach ($it2 as $item) {
            if (!$item->isFile()) continue;
            $rel  = trim(str_replace($extractTo, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            $dest = $base.DIRECTORY_SEPARATOR.$rel;
            $destDir = dirname($dest);

            if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
            if (!is_writable($destDir)) { $failed[] = "$rel (directorio no escribible: $destDir)"; continue; }
            if (file_exists($dest) && !is_writable($dest)) { $failed[] = "$rel (archivo no escribible)"; continue; }

            if (@copy($item->getPathname(), $dest)) {
                $copied[] = $rel;
                // Actualizar asset-manifest si corresponde a public/js o public/css
                $this->updateAssetManifest($rel);
            }
            else { $err = error_get_last(); $failed[] = "$rel (copy error: ".($err['message'] ?? 'desconocido').")"; }
        }

        try { File::deleteDirectory($extractTo); } catch (\Exception $e) {}

        $status = "ZIP instalado. Copiados: ".count($copied).(count($failed) ? " — Fallidos: ".count($failed) : '');
        $this->logAction($request, 'zip.install', $status);

        $report = [
            'ts'      => date('Y-m-d H:i:s'),
            'zip'     => basename($tmpZip),
            'copied'  => $copied,
            'failed'  => $failed,
            'backup'  => $backupName,
            'ip'      => $request->ip(),
            'user'    => $request->user()->email ?? '-',
        ];
        @file_put_contents($dirs['logs'].'/install_'.date('Ymd_His').'.json', json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        return back()->with('status', $status)->with('copied', $copied)->with('import_errors', $failed);
    }

    private function uploadSingle(Request $request)
    {
        $request->validate(['file' => ['required','file'], 'dest' => ['required','string']]);
        [$storeBase, $dirs] = $this->ensureStorageBase();

        $rel = trim(str_replace(['\\','..'], ['/',''], $request->input('dest')), '/');
        if ($rel === '') return back()->withErrors(['dest'=>'Ruta destino inválida'])->withInput();

        $base = base_path();
        $dest = $base.DIRECTORY_SEPARATOR.$rel;
        $destDir = dirname($dest);

        $backupName = 'single_'.date('Ymd_His').'.zip';
        $backupPath = $dirs['backups'].'/'.$backupName;
        $this->createBackupZip($backupPath, is_file($dest) ? [$rel=>$dest] : []);

        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
        if (!is_writable($destDir)) return back()->withErrors(['dest'=>"Directorio no escribible: $destDir"]);
        if (file_exists($dest) && !is_writable($dest)) return back()->withErrors(['dest'=>"Archivo no escribible: $dest"]);

        if (@copy($request->file('file')->getRealPath(), $dest)) {
            $this->logAction($request, 'file.install', "Reemplazado $rel (backup $backupName)");
            // Actualizar asset-manifest si corresponde a public/js o public/css
            $this->updateAssetManifest($rel);
            return back()->with('status', "Archivo instalado: $rel");
        } else {
            $err = error_get_last();
            return back()->withErrors(['file'=>"Error al copiar: ".($err['message'] ?? 'desconocido')]);
        }
    }

    public function run(Request $request)
    {
        $request->validate(['cmd' => ['required','string']]);
        $allowed = [
            'check_perms'          => '__check_perms__',
            'restore_latest'       => '__restore_latest__',
            'package_full'         => '__package_full__',
            'migrate'              => 'migrate',
            'seed_payment_methods' => 'db:seed --class=Database\Seeders\PaymentMethodSeeder',
            'seed_nov_opening'     => 'db:seed --class=Database\Seeders\NovemberOpeningSeeder',
            'optimize_clear'       => 'optimize:clear',
            'cache_clear'          => 'cache:clear',
            'config_cache'         => 'config:cache',
            'route_cache'          => 'route:cache',
        ];
        $cmd = $allowed[$request->cmd] ?? null;
        if (!$cmd) return back()->withErrors(['cmd'=>'Comando no permitido']);

        if ($cmd === '__check_perms__') {
            $report = $this->checkPermsReport();
            $this->logAction($request, 'perms.check', 'Chequeo de permisos');
            return back()->with('status', 'Chequeo de permisos ejecutado')->with('output', $report);
        }

        if ($cmd === '__restore_latest__') {
            [$storeBase, $dirs] = $this->ensureStorageBase();
            $latest = $this->latestBackup($dirs['backups']);
            if (!$latest) return back()->withErrors(['cmd'=>'No hay backups para restaurar.']);
            $res = $this->restoreBackup($dirs['backups'].'/'.$latest);
            $this->logAction($request, 'backup.restore', "Restaurado $latest: $res");
            return back()->with('status', "Backup restaurado: $latest")->with('output', $res);
        }

        if ($cmd === '__package_full__') {
            $opts = [
                'include_vendor'       => (bool)$request->boolean('include_vendor'),
                'include_node_modules' => (bool)$request->boolean('include_node_modules'),
                'include_storage_logs' => (bool)$request->boolean('include_storage_logs'),
                'include_api'          => (bool)$request->boolean('include_api'),
                'include_brdge'        => (bool)$request->boolean('include_brdge'),
            ];
            [$storeBase, $dirs] = $this->ensureStorageBase();
            $result = $this->createFullPackage($opts, $dirs);
            $msg = "Paquete creado: {$result['zip']} | Archivos: {$result['files']} | Omitidos: {$result['skipped']} | ".($result['db'] ?? 'DB: n/a');
            $this->logAction($request, 'package.full', $msg);
            return back()->with('status', $msg);
        }

        $exit = Artisan::call($cmd);
        $output = Artisan::output();
        $this->logAction($request, 'artisan', "php artisan {$cmd} (exit {$exit})");
        return back()->with('status', "Comando ejecutado: {$cmd} (exit {$exit})")->with('output', $output);
    }

    public function downloadBackup(Request $request, string $file)
    {
        [$storeBase, $dirs] = $this->ensureStorageBase();
        $safe = basename($file);
        $path = $dirs['backups'].'/'.$safe;
        if (!is_file($path)) abort(404);

        return new StreamedResponse(function() use ($path) {
            $stream = fopen($path, 'rb');
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$safe.'"',
        ]);
    }

    private function createFullPackage(array $opts, array $dirs): array
{
    if (!is_dir($dirs['backups'])) { @mkdir($dirs['backups'], 0775, true); }
    if (!is_dir($dirs['backups']) || !is_writable($dirs['backups'])) {
        return ['zip' => '(no creado)', 'files' => 0, 'skipped' => 0, 'db' => 'ERROR: backups no escribible'];
    }

    $base     = base_path();
    $zipName  = 'project_full_' . date('Ymd_His') . '.zip';
    $finalZip = rtrim($dirs['backups'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zipName;

    // Exclusiones por defecto: se pueden re-incluir marcando checkboxes en la UI
    $exclude = [
        '.git',
        '.idea',
        'tests',
        'vendor',
        'node_modules',
        'storage/logs',
        'bootstrap/cache/*.php',
        'api',
        'bridge',
    ];

    if (!empty($opts['include_vendor'])) {
        $exclude = array_values(array_filter($exclude, fn($e) => $e !== 'vendor'));
    }
    if (!empty($opts['include_node_modules'])) {
        $exclude = array_values(array_filter($exclude, fn($e) => $e !== 'node_modules'));
    }
    if (!empty($opts['include_storage_logs'])) {
        $exclude = array_values(array_filter($exclude, fn($e) => $e !== 'storage/logs'));
    }
    if (!empty($opts['include_api'])) {
        $exclude = array_values(array_filter($exclude, fn($e) => $e !== 'api'));
    }
    if (!empty($opts['include_bridge'])) {
        $exclude = array_values(array_filter($exclude, fn($e) => $e !== 'bridge'));
    }

    // Construimos argumentos -x para zip
    $exArgs = [];
    foreach ($exclude as $e) {
        $exArgs[] = "-x '{$e}/*'";
    }
    $ex = $exArgs ? (' ' . implode(' ', $exArgs)) : '';

    // Dump de BD (si está disponible la herramienta)
    $dbDump = 'omitido';
    if (function_exists('shell_exec')) {
        $dumpFile = $dirs['backups'].'/db_'.date('Ymd_His').'.sql';
        @mkdir(dirname($dumpFile), 0775, true);
        $cmdDump = env('UPDATER_DBDUMP_CMD'); // opcional
        if ($cmdDump) {
            $out = shell_exec(str_replace(['{file}'], [escapeshellarg($dumpFile)], $cmdDump).' 2>&1');
            if (is_file($dumpFile)) {
                $dbDump = basename($dumpFile);
            } else {
                $dbDump = 'ERROR: '.$out;
            }
        }
    }

    // Creamos el zip usando zip -r desde la raíz del proyecto
    $cwd = getcwd();
    chdir($base);
    $cmd = "zip -r ".escapeshellarg($finalZip)." .{$ex}";
    $output = [];
    $exit = 0;
    exec($cmd, $output, $exit);
    chdir($cwd ?: $base);

    return [
        'zip'    => $exit === 0 ? basename($finalZip) : '(error)',
        'files'  => $exit === 0 ? count($output) : 0,
        'skipped'=> 0,
        'db'     => $dbDump,
    ];
}

private function ensureStorageBase(): array
    {
        $custom = env('UPDATER_BASE');
        $storeBase = $custom ? rtrim($custom, '/')
                             : rtrim(realpath(base_path('..')) ?: base_path('..'), '/').'/updater_data';
        $dirs = [
            'root'    => $storeBase,
            'uploads' => $storeBase.'/uploads',
            'backups' => $storeBase.'/backups',
            'logs'    => $storeBase.'/logs',
        ];
        foreach ($dirs as $p) { if (!is_dir($p)) @mkdir($p, 0775, true); }
        return [$storeBase, $dirs];
    }

    private function createBackupZip(string $zipPath, array $files): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) { touch($zipPath); return; }
        foreach ($files as $rel => $abs) {
            if (is_file($abs)) { $zip->addFile($abs, $rel); }
        }
        $zip->close();
    }

    private function restoreBackup(string $zipPath): string
    {
        if (!is_file($zipPath)) return 'Backup no encontrado';
        $base = base_path();
        $temp = storage_path('app/updater_restore_'.uniqid());
        @mkdir($temp, 0775, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) return 'No se pudo abrir el backup';
        $zip->extractTo($temp); $zip->close();

        $count = 0; $failed = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temp, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $item) {
            if (!$item->isFile()) continue;
            $rel = trim(str_replace($temp, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            $dest = $base.DIRECTORY_SEPARATOR.$rel;
            $dir = dirname($dest);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (!@copy($item->getPathname(), $dest)) { $failed++; } else { $count++; }
        }
        try { File::deleteDirectory($temp); } catch (\Exception $e) {}
        return "Restaurados: $count, Fallidos: $failed";
    }

    private function listBackups(string $dir): array
    {
        if (!is_dir($dir)) return [];
        $files = array_values(array_filter(scandir($dir) ?: [], function($f){ return str_ends_with($f, '.zip'); }));
        rsort($files);
        return $files;
    }

    private function latestBackup(string $dir): ?string
    {
        $list = $this->listBackups($dir);
        return $list[0] ?? null;
    }

    public function deleteBackup(\Illuminate\Http\Request $request, string $file)
{
    [$storeBase, $dirs] = $this->ensureStorageBase();
    $safe = basename($file);
    $path = $dirs['backups'].'/'.$safe;

    if (!is_file($path)) {
        return back()->withErrors(['backup' => 'El backup indicado no existe o ya fue eliminado.']);
    }

    if (!@unlink($path)) {
        $err = error_get_last();
        return back()->withErrors(['backup' => 'No se pudo eliminar el backup: '.(($err['message'] ?? 'desconocido'))]);
    }

    $this->logAction($request, 'backup.delete', "file={$safe}");
    return back()->with('status', "Backup eliminado: {$safe}");
}


private function readLogTail(string $path, int $maxBytes = 2000): string
    {
        if (!is_file($path)) return '';
        $size = filesize($path);
        $fh = fopen($path, 'rb');
        if ($size > $maxBytes) fseek($fh, -$maxBytes, SEEK_END);
        $data = stream_get_contents($fh);
        fclose($fh);
        return (string) $data;
    }

    private function checkPermsReport(): string
    {
        $paths = [
            'app' => base_path('app'),
            'resources' => base_path('resources'),
            'database' => base_path('database'),
            'public' => public_path(),
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            '.env' => base_path('.env'),
        ];
        $lines = [];
        $lines[] = "== Permisos del proyecto ==\nUsuario actual: ".get_current_user()." (uid ".getmyuid().")\n";
        foreach ($paths as $label => $p) {
            $exists = file_exists($p);
            $isDir = is_dir($p);
            $isFile = is_file($p);
            $w = $exists ? (is_writable($p) ? 'OK' : 'NO') : '—';
            $perm = $exists ? substr(sprintf('%o', fileperms($p)), -4) : '----';
            $owner = $exists ? (function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($p))['name'] ?? fileowner($p)) : fileowner($p)) : '—';
            $group = $exists ? (function_exists('posix_getgrgid') ? (posix_getgrgid(filegroup($p))['name'] ?? filegroup($p)) : filegroup($p)) : '—';
            $lines[] = sprintf("[%s] %s | tipo=%s | writable=%s | perms=%s | owner=%s:%s",
                $label, $p, $isDir ? 'dir' : ($isFile ? 'file' : 'n/a'), $w, $perm, $owner, $group
            );
        }
        [$storeBase, $dirs] = $this->ensureStorageBase();
        $lines[] = "\nAlmacenamiento externo: $storeBase";
        foreach ($dirs as $k=>$p) { $lines[] = "- $k: $p (".(is_writable($p)?'OK':'NO').")"; }

        $tips = [
            "",
            "== Sugerencias ==",
            "• Ajusta permisos si ves writable=NO:",
            "  sudo chown -R www-data:www-data " . base_path(),
            "  sudo chown -R www-data:www-data " . dirname(base_path()) . "/updater_data",
            "  sudo find " . base_path() . " -type d -exec chmod 775 {} \\;",
            "  sudo find " . base_path() . " -type f -exec chmod 664 {} \\;",
        ];
        return implode("\n", array_merge($lines, $tips));
    }

    
/**
 * Actualiza el asset-manifest.json cuando se reemplaza un archivo en public/js o public/css.
 *
 * @param string $rel Ruta relativa desde la raíz del proyecto (por ejemplo: "public/js/kanban.js")
 */
private function updateAssetManifest(string $rel): void
{
    // Normalizar separadores
    $rel = str_replace('\\', '/', $rel);

    // Solo nos interesan archivos dentro de public/js o public/css
    if (!preg_match('~^public/(js|css)/.+~', $rel)) {
        return;
    }

    // Obtener ruta relativa desde /public
    $publicRel = preg_replace('~^public/~', '', $rel);
    $fullPath  = public_path($publicRel);

    if (!is_file($fullPath)) {
        return;
    }

    $manifestPath = public_path('asset-manifest.json');
    if (!file_exists($manifestPath)) {
        file_put_contents($manifestPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $data = json_decode(file_get_contents($manifestPath), true);
    if (!is_array($data)) {
        $data = [];
    }

    $hash = substr(md5_file($fullPath), 0, 10) . '_' . time();
    $data[$publicRel] = $hash;

    file_put_contents(
        $manifestPath,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}


private function logAction(Request $request, string $action, string $detail): void
    {
        [$storeBase, $dirs] = $this->ensureStorageBase();
        $logFile = $dirs['logs'].'/updater.log';
        $line = sprintf("[%s] %s | ip=%s | user=%s | %s\n",
            date('Y-m-d H:i:s'), $action, $request->ip(), $request->user()->email ?? '-', $detail
        );
        file_put_contents($logFile, $line, FILE_APPEND);
    }
}
