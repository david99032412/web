<?php
// forum/functions.php - PHP 8.2+ KOMPATIBILIS JAVÍTÁS (FULLOS BBCODE MOTORRAL)

if (session_status() == PHP_SESSION_NONE) session_start();

// --- BIZTONSÁGOS TISZTÍTÁS (Null kezeléssel) ---
function clean($data) {
    // A ?? '' biztosítja, hogy ha null jön, üres string legyen, így nem dob Deprecated hibát
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

// --- BELÉPÉS ELLENŐRZÉS ---
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// --- MODERÁTOR JOG ELLENŐRZÉS ---
function canModerate() {
    // AdminLevel 1-től felfelé (vagy ahogy beállítod)
    return (isset($_SESSION['admin_level']) && $_SESSION['admin_level'] >= 1);
}

// --- AKTIVITÁS FRISSÍTÉSE ---
function updateActivity() {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare("UPDATE accounts SET last_activity = NOW() WHERE accountId = ?");
            $stmt->execute([$uid]);
        } catch (Exception $e) {}
    }
}
if (isLoggedIn()) updateActivity();

// --- NAPLÓZÁS ---
function logModAction($userId, $action, $targetInfo) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO forum_mod_logs (user_id, action, target_info) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $targetInfo]);
    } catch (Exception $e) {}
}

// --- RANGOK (JELVÉNYEKKEL) ---
function getUserRank($level) {
    $level = (int)($level ?? 0);
    
    // Itt állítsd be a szinteket a szervered szerint!
    switch ($level) {
        case 11: return ['name' => 'Tulajdonos', 'color' => '#d32f2f', 'class' => 'rank-owner'];
        case 8:  return ['name' => 'Főadmin', 'color' => '#2980b9', 'class' => 'rank-mainadmin'];
        case 5:  
        case 4:  
        case 3:  
        case 2:  
        case 1:  return ['name' => 'Adminisztrátor', 'color' => '#27ae60', 'class' => 'rank-admin'];
        // Ha van VIP oszlopod, azt külön kéne kezelni, de most tegyük fel, hogy szint alapú
        default: return ['name' => 'Felhasználó', 'color' => '#95a5a6', 'class' => 'rank-member'];
    }
}

// --- XENFORO 1:1 BBCODE PARSER ---
function parseBBCode($text) {
    if (empty($text)) return '';
    
    // XSS védelem (HTML tagek blokkolása)
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); 

    // Kód blokk kivétele (hogy a [code] belsejét ne formázza BBCode-ként)
    $codeBlocks = [];
    $text = preg_replace_callback('/\[code\](.*?)\[\/code\]/is', function($matches) use (&$codeBlocks) {
        $id = '@@CODEBLOCK' . count($codeBlocks) . '@@';
        $codeBlocks[$id] = '<div class="bbCodeBlock-code"><code>' . $matches[1] . '</code></div>';
        return $id;
    }, $text);

    // Sortörések HTML <br>-re
    $text = nl2br($text);

    // Formázási szabályok
    $tags = [
        '/\[b\](.*?)\[\/b\]/is' => '<strong>$1</strong>',
        '/\[i\](.*?)\[\/i\]/is' => '<em>$1</em>',
        '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
        '/\[s\](.*?)\[\/s\]/is' => '<s>$1</s>',
        '/\[center\](.*?)\[\/center\]/is' => '<div style="text-align:center;">$1</div>',
        '/\[color=([a-zA-Z0-9#]+)\](.*?)\[\/color\]/is' => '<span style="color:$1;">$2</span>',
        '/\[size=([0-9]+)\](.*?)\[\/size\]/is' => '<span style="font-size:$1px;">$2</span>',
        '/\[img\](.*?)\[\/img\]/is' => '<img src="$1" alt="Kép" style="max-width:100%; border-radius:4px; margin:10px 0; border:1px solid var(--border-color);">',
        '/\[url=([^\]]+)\](.*?)\[\/url\]/is' => '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:var(--primary-color); font-weight:600;">$2</a>',
        '/\[url\](.*?)\[\/url\]/is' => '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:var(--primary-color); font-weight:600;">$1</a>',
        
        // XenForo Idézetek (Quote)
        '/\[quote="([^\]]+)"\](.*?)\[\/quote\]/is' => '<blockquote class="bbCodeBlock-quote"><div class="quote-header">$1 írta:</div><div class="quote-content">$2</div></blockquote>',
        '/\[quote\](.*?)\[\/quote\]/is' => '<blockquote class="bbCodeBlock-quote"><div class="quote-content">$1</div></blockquote>',
        
        // XenForo Spoilerek
        '/\[spoiler="([^\]]+)"\](.*?)\[\/spoiler\]/is' => '<div class="bbCodeBlock-spoiler"><button type="button" class="spoiler-button" onclick="this.nextElementSibling.classList.toggle(\'show\')"><span>SPOILER:</span> $1</button><div class="spoiler-content">$2</div></div>',
        '/\[spoiler\](.*?)\[\/spoiler\]/is' => '<div class="bbCodeBlock-spoiler"><button type="button" class="spoiler-button" onclick="this.nextElementSibling.classList.toggle(\'show\')">SPOILER (Kattints a megnyitáshoz)</button><div class="spoiler-content">$1</div></div>',
        
        // Listák
        '/\[list\](.*?)\[\/list\]/is' => '<ul style="margin: 10px 0; padding-left: 20px;">$1</ul>',
        '/\[\*\](.*?)(\n|\r\n?|<br \/>)/is' => '<li>$1</li>'
    ];
    
    // Szabályok alkalmazása
    foreach ($tags as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text);
    }

    // Kód blokkok visszahelyezése
    foreach ($codeBlocks as $id => $html) {
        $text = str_replace($id, $html, $text);
    }
    
    return $text;
}

// --- AVATAR ---
function getAvatar($user) {
    if (!empty($user['forum_avatar'])) return htmlspecialchars($user['forum_avatar']);
    $name = urlencode($user['username'] ?? 'User');
    return "https://ui-avatars.com/api/?name=$name&background=30363d&color=fff&size=128";
}

// --- IDŐ KIÍRÁS (JAVÍTOTT: DateInterval::$w FIX) ---
function time_elapsed_string($datetime, $full = false) {
    // Ellenőrzés, ha nincs dátum
    if (empty($datetime)) return 'Soha';

    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Manuális hét számítás a hiba elkerülése végett
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    // Értékek hozzárendelése változókhoz
    $string = array(
        'y' => 'éve',
        'm' => 'hónapja',
        'w' => 'hete',
        'd' => 'napja',
        'h' => 'órája',
        'i' => 'perce',
        's' => 'másodperce',
    );

    $result = array();
    
    foreach ($string as $k => $v) {
        // Érték kiválasztása
        if ($k == 'w') {
            $value = $weeks;
        } elseif ($k == 'd') {
            $value = $days;
        } else {
            // A $diff objektum beépített tulajdonságai (y, m, h, i, s)
            $value = $diff->$k;
        }

        if ($value > 0) {
            $result[] = $value . ' ' . $v;
        }
    }

    if (!$full) $result = array_slice($result, 0, 1);
    
    // Ha üres (pl. 0 másodperc), akkor "épp most"
    return $result ? implode(', ', $result) . ' volt' : 'épp most';
}
?>
