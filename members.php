<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);
$success = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'user_deleted') {
    $success = 'Usuário banido e excluído com sucesso.';
}

// Buscar todos os membros
$search = trim($_GET['search'] ?? '');
$params = [];
$where = '';

if (!empty($search)) {
    $where = "WHERE nome LIKE ? OR nome_usuario LIKE ? OR email LIKE ?";
    $searchTerm = '%' . $search . '%';
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$stmt = $pdo->prepare("SELECT u.*, 
    (SELECT COUNT(*) FROM postagens WHERE user_id = u.id) as total_posts,
    (SELECT COUNT(*) FROM respostas WHERE user_id = u.id) as total_replies
    FROM usuarios u $where ORDER BY u.created_at DESC");
$stmt->execute($params);
$members = $stmt->fetchAll();

$userFoto = $user['foto'] !== 'default.png' && file_exists('uploads/' . $user['foto'])
    ? 'uploads/' . $user['foto']
    : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($user['nome']) . '&backgroundColor=6c5ce7';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Membros da comunidade SGDC Forum">
    <title>Membros - SGDC Forum</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="forum.php" class="navbar-brand">
                <span>💬</span> SGDC Forum
            </a>
            <button class="menu-toggle" onclick="document.querySelector('.navbar-nav').classList.toggle('open')">☰</button>
            <ul class="navbar-nav">
                <li><a href="forum.php" id="navForum">🏠 Fórum</a></li>
                <li><a href="members.php" class="active" id="navMembers">👥 Membros</a></li>
                <li><a href="profile.php" id="navProfile">👤 Meu Perfil</a></li>
                <li>
                    <div class="navbar-user">
                        <img src="<?= $userFoto ?>" alt="<?= sanitize($user['nome']) ?>">
                        <span class="user-name"><?= sanitize($user['nome_usuario']) ?></span>
                    </div>
                </li>
                <li><a href="logout.php" id="navLogout" style="color: var(--danger);">🚪 Sair</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= sanitize($success) ?></div>
        <?php endif; ?>
        <div class="page-header">
            <h1>👥 <span>Membros</span> da Comunidade</h1>
            <span class="badge badge-primary" style="font-size:0.85rem;padding:6px 14px;">
                <?= count($members) ?> membro<?= count($members) != 1 ? 's' : '' ?>
            </span>
        </div>

        <!-- Search -->
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Buscar membros por nome, usuário ou e-mail..." 
                       value="<?= sanitize($search) ?>" id="searchMembers">
            </form>
        </div>

        <!-- Members Grid -->
        <?php if (empty($members)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <h3>Nenhum membro encontrado</h3>
                <p>Tente uma busca diferente.</p>
            </div>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($members as $index => $member): ?>
                    <?php 
                    $memberFoto = $member['foto'] !== 'default.png' && file_exists('uploads/' . $member['foto'])
                        ? 'uploads/' . $member['foto']
                        : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($member['nome']) . '&backgroundColor=6c5ce7';
                    ?>
                    <div class="user-card" style="animation-delay: <?= $index * 0.05 ?>s;">
                        <a href="profile.php?id=<?= $member['id'] ?>">
                            <img src="<?= $memberFoto ?>" alt="<?= sanitize($member['nome']) ?>">
                        </a>
                        <h3><a href="profile.php?id=<?= $member['id'] ?>" style="color:var(--text-primary);"><?= sanitize($member['nome']) ?></a></h3>
                        <div class="username">@<?= sanitize($member['nome_usuario']) ?></div>
                        <div style="margin-top:8px;">
                            <?php if ($member['role'] === 'admin'): ?>
                                <span class="badge badge-admin" style="font-size:0.65rem;">🛡️ ADMIN</span>
                            <?php else: ?>
                                <?php $lvl = getXPLevel($member['xp']); ?>
                                <span class="badge badge-rank-<?= $lvl ?>" style="font-size:0.65rem;">🏅 Nível <?= $lvl ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:16px;justify-content:center;margin-top:8px;">
                            <span style="font-size:0.8rem;color:var(--text-secondary);">
                                📝 <?= $member['total_posts'] ?> post<?= $member['total_posts'] != 1 ? 's' : '' ?>
                            </span>
                            <span style="font-size:0.8rem;color:var(--text-secondary);">
                                💬 <?= $member['total_replies'] ?> resposta<?= $member['total_replies'] != 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <a href="profile.php?id=<?= $member['id'] ?>" class="btn btn-secondary btn-sm" style="margin-top:14px;">
                            Ver Perfil →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        // Auto-hide success alerts
        document.querySelectorAll('.alert-success').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 4000);
        });
    </script>
</body>
</html>
