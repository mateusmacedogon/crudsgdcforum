<?php
session_start();

$host = 'localhost';
$dbname = 'sgdc_forum';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "<br><br>Execute <a href='setup.php'>setup.php</a> primeiro para criar o banco de dados.");
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ' atrás';
    if ($diff->m > 0) return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '') . ' atrás';
    if ($diff->d > 0) return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
    if ($diff->h > 0) return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
    if ($diff->i > 0) return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
    return 'agora mesmo';
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function isAdmin($user) {
    return isset($user['role']) && $user['role'] === 'admin';
}

function getXPLevel($xp) {
    if ($xp < 50) return 1;
    if ($xp < 150) return 2;
    if ($xp < 350) return 3;
    if ($xp < 750) return 4;
    return 5;
}

function getRankName($level) {
    switch ($level) {
        case 1: return 'Novato';
        case 2: return 'Aprendiz';
        case 3: return 'Veterano';
        case 4: return 'Especialista';
        case 5: return 'Lenda';
        default: return 'Desconhecido';
    }
}

function getNextLevelXP($level) {
    switch ($level) {
        case 1: return 50;
        case 2: return 150;
        case 3: return 350;
        case 4: return 750;
        default: return 750; // Max level reached
    }
}

?>
