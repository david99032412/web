<?php
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_username']);
}

function getAdminTitle($level) {
    $titles = [
        11 => "Tulajdonos", 10 => "Fejlesztő", 9 => "SysEngineer", 8 => "Manager",
        7 => "SzuperAdmin", 6 => "FőAdmin", 5 => "Admin V", 4 => "Admin IV",
        3 => "Admin III", 2 => "Admin II", 1 => "Admin I"
    ];
    return $titles[(int)$level] ?? "Játékos";
}

function sendDiscordWebhook($title, $message, $color = 3447003) {
    if (!defined('DISCORD_WEBHOOK') || empty(DISCORD_WEBHOOK)) return;
    
    $data = ["embeds" => [["title" => $title, "description" => $message, "color" => $color]]];
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context  = stream_context_create($options);
    return file_get_contents(DISCORD_WEBHOOK, false, $context);
}
?>