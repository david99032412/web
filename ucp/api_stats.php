<?php
// api_stats.php - 100% GOLYÓÁLLÓ VERZIÓ (Linux permission, UTF-8 és 0-byte védelemmel)
ob_start(); 

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) { 
    http_response_code(403); 
    exit; 
}

$log_path = "/home/mta/mods/deathmatch/logs/server.log";

function formatBytes($bytes, $precision = 2) { 
    $units = ['B', 'KB', 'MB', 'GB', 'TB']; 
    $bytes = max($bytes, 0); 
    if ($bytes < 1) return '0 B';
    $pow = floor(log($bytes) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

function formatShortNumber($num) {
    if ($num > 1000000) return round($num / 1000000, 1) . 'M';
    if ($num > 1000) return round($num / 1000, 1) . 'k';
    return number_format((float)$num);
}

$action = $_GET['req'] ?? 'stats';

if ($action === 'stats') {
    $cpuUsage = '0%';
    if (function_exists('sys_getloadavg')) {
        $load = @sys_getloadavg();
        $cores = 1;
        if (file_exists('/proc/cpuinfo')) {
            $cores = max(1, substr_count(@file_get_contents('/proc/cpuinfo'), 'processor'));
        }
        if (is_array($load) && isset($load[0])) {
            $cpuUsage = round(($load[0] / $cores) * 100, 1) . '%';
        }
    }

    $ramUsage = '0 MB / 0 MB';
    $free = @shell_exec('free -m');
    if ($free) {
        $free_arr = explode("\n", trim($free));
        if (isset($free_arr[1])) {
            $mem = explode(" ", preg_replace('/\s+/', ' ', $free_arr[1]));
            if (isset($mem[1]) && isset($mem[2])) {
                $ramUsage = $mem[2] . 'MB / ' . $mem[1] . 'MB';
            }
        }
    }

    $diskUsage = '0 MB';
    $disk_total = @disk_total_space("/");
    $disk_free = @disk_free_space("/");
    if ($disk_total && $disk_free) {
        $disk_used = $disk_total - $disk_free;
        $diskUsage = formatBytes($disk_used) . ' / ' . formatBytes($disk_total);
    }

    $uptime_str = @file_get_contents('/proc/uptime');
    $uptime = 'Ismeretlen';
    if ($uptime_str) {
        $uptime_sec = floatval(explode(' ', $uptime_str)[0]);
        $d = floor($uptime_sec / 86400);
        $h = floor(($uptime_sec % 86400) / 3600);
        $m = floor(($uptime_sec % 3600) / 60);
        $uptime = "{$d} nap, {$h}ó {$m}p";
    }

    $rx = 0; $tx = 0;
    $lines = @file('/proc/net/dev');
    if ($lines) {
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false && strpos($line, 'lo:') === false) {
                $line = str_replace(':', ': ', $line);
                $parts = preg_split('/\s+/', trim($line));
                if (isset($parts[1]) && isset($parts[9])) {
                    $rx += (float)$parts[1];
                    $tx += (float)$parts[9];
                }
            }
        }
    }
    
    $tmpFile = sys_get_temp_dir() . '/ucp_net_speed.json';
    $currentNet = ['rx' => $rx, 'tx' => $tx, 'time' => microtime(true)];
    $speedRx = 0; $speedTx = 0;

    if (file_exists($tmpFile)) {
        $lastNet = @json_decode(@file_get_contents($tmpFile), true);
        if ($lastNet && isset($lastNet['time'])) {
            $timeDiff = $currentNet['time'] - $lastNet['time'];
            if ($timeDiff > 0 && $timeDiff < 10) { 
                $speedRx = max(0, ($currentNet['rx'] - $lastNet['rx']) / $timeDiff);
                $speedTx = max(0, ($currentNet['tx'] - $lastNet['tx']) / $timeDiff);
            }
        }
    }
    @file_put_contents($tmpFile, json_encode($currentNet));

    $onlinePlayers = 0;
    $accCount = 0;
    $ecoSum = 0;

    try { $onlinePlayers = (int)$pdo->query("SELECT count(*) FROM characters WHERE lastOnline > (NOW() - INTERVAL 15 MINUTE)")->fetchColumn(); } catch(Exception $e) {}
    try { $accCount = (float)$pdo->query("SELECT count(*) FROM accounts")->fetchColumn(); } catch(Exception $e) {}
    try { $ecoSum = (float)$pdo->query("SELECT SUM(money+bankMoney) FROM characters")->fetchColumn(); } catch(Exception $e) {}

    $response = [
        'ptero' => [
            'online' => true,
            'cpu' => $cpuUsage,
            'ram' => $ramUsage,
            'disk' => $diskUsage,
            'net_rx' => formatBytes($speedRx) . '/s',
            'net_tx' => formatBytes($speedTx) . '/s',
            'uptime' => $uptime
        ],
        'db' => [
            'online_players' => $onlinePlayers,
            'accounts' => formatShortNumber($accCount),
            'economy' => '$' . formatShortNumber($ecoSum)
        ]
    ];

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($action === 'console') {
    $content = "Nem található a server.log fájl ezen az útvonalon: " . $log_path;
    clearstatcache(); // Cache törlése a pontos mérethez
    if (file_exists($log_path)) {
        $fileSize = filesize($log_path);
        if ($fileSize > 0) { // Védelem a 0 byte-os fájlok ellen
            $readSize = min($fileSize, 5000);
            $fp = @fopen($log_path, 'r');
            if ($fp) {
                fseek($fp, -$readSize, SEEK_END);
                $content = fread($fp, $readSize);
                fclose($fp);
                
                $content = mb_convert_encoding((string)$content, 'UTF-8', 'UTF-8');
                $lines = explode("\n", $content);
                $lines = array_slice($lines, -25);
                $content = implode("\n", $lines);
            }
        } else {
            $content = "A szerver log jelenleg teljesen üres.";
        }
    }
    
    ob_end_clean(); 
    header('Content-Type: application/json');
    echo json_encode(['content' => htmlspecialchars($content, ENT_IGNORE, 'UTF-8')]);
    exit;
}

if ($action === 'debug_logs') {
    $errors = [];
    $warnings = [];
    
    $logFiles = [
        '/var/www/web/ucp/weberrors.log',
        '/var/log/apache2/error.log',
        '/var/log/apache2/access.log'
    ];

    $allLines = [];
    foreach ($logFiles as $file) {
        clearstatcache(); // Cache törlése a pontos mérethez
        if (@file_exists($file)) {
            if (@is_readable($file)) {
                $fileSize = filesize($file);
                if ($fileSize > 0) { // KULCSFONTOSSÁGÚ VÉDELEM: Ne akarjunk 0 byte-ot olvasni!
                    $readSize = min($fileSize, 50000); 
                    $fp = @fopen($file, 'r');
                    if ($fp) {
                        fseek($fp, -$readSize, SEEK_END);
                        $content = fread($fp, $readSize);
                        fclose($fp);
                        
                        $content = mb_convert_encoding((string)$content, 'UTF-8', 'UTF-8');
                        $lines = explode("\n", $content);
                        foreach($lines as $line) {
                            if (trim($line) !== '') {
                                $allLines[] = ['file' => $file, 'text' => $line];
                            }
                        }
                    }
                }
            } else {
                $warnings[] = "[Linux Jogosultság] Nincs jogom olvasni ezt a fájlt: " . basename($file);
            }
        }
    }

    foreach ($allLines as $data) {
        $line = $data['text'];
        $file = $data['file'];
        
        $cleanLine = htmlspecialchars(trim($line), ENT_IGNORE, 'UTF-8');
        
        if (strpos($file, 'access.log') !== false) {
            if (preg_match('/\" ([45]\d{2}) /', $line, $matches)) {
                $statusCode = (int)$matches[1];
                $shortLine = preg_replace('/^.*? - - \[.*?\] \"/', '"', $cleanLine);
                
                if ($statusCode >= 500) {
                    $errors[] = "[ACCESS] " . $shortLine;
                } elseif ($statusCode >= 400 && $statusCode != 401) { 
                    $warnings[] = "[ACCESS] " . $shortLine;
                }
            }
        } else {
            // Veszélyes végtelen ciklus megszüntetve, helyette biztonságos reguláris kifejezés
            $shortLine = preg_replace('/^(\[.*?\]\s*)+/', '', $cleanLine);

            if (strpos($shortLine, 'Fatal error') !== false || strpos($shortLine, 'Parse error') !== false || strpos($shortLine, 'Uncaught') !== false || strpos($shortLine, 'SQLSTATE') !== false || preg_match('/error:/i', $shortLine)) {
                $errors[] = $shortLine;
            } 
            elseif (strpos($shortLine, 'Warning') !== false || strpos($shortLine, 'Notice') !== false || strpos($shortLine, 'Deprecated') !== false) {
                $warnings[] = $shortLine;
            }
        }
    }

    $errors = array_values(array_unique($errors));
    $warnings = array_values(array_unique($warnings));

    ob_end_clean(); 
    header('Content-Type: application/json');
    echo json_encode([
        'errors' => array_slice($errors, -15), 
        'warnings' => array_slice($warnings, -15)
    ]);
    exit;
}

if ($action === 'send_cmd') { exit; }

ob_end_clean();
exit;