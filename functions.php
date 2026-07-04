<?php
// functions.php - FIVEM QBCORE & UCP FUNKCIÓK

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

// --- A TE SAJÁT RANGRENDSZERED ---
function getUserRank($level) {
    switch ((int)$level) {
        case 10:
            return ['name' => 'Tulajdonos', 'color' => '#ff0000'];
        case 5:
            return ['name' => 'Admin', 'color' => '#ff5555'];
        case 3:
            return ['name' => 'Moderátor', 'color' => '#ffaa00'];
        default:
            return ['name' => 'Játékos', 'color' => '#8b949e'];
    }
}

// Visszafelé kompatibilitás a korábbi UCP kódokhoz (Admin Panel, Support ticketek)
function getAdminTitle($level) {
    $rank = getUserRank($level);
    return $rank['name'];
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
