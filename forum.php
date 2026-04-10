<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);
$success = '';
$error = '';

// Criar nova postagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_post') {
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');

        if (empty($titulo) || empty($conteudo)) {
            $error = 'Preencha o título e o conteúdo da postagem.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO postagens (user_id, titulo, conteudo) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $titulo, $conteudo]);
            // Ganho de XP (+10)
            $pdo->prepare("UPDATE usuarios SET xp = xp + 10 WHERE id = ?")->execute([$user['id']]);
            $success = 'Postagem criada com sucesso! 🎉 +10 XP';
            // Limpar POST para evitar reenvio
            header('Location: forum.php?msg=created');
            exit;
        }
    } elseif ($_POST['action'] === 'delete_post') {
        $post_id = intval($_POST['post_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT user_id FROM postagens WHERE id = ?");
        $stmt->execute([$post_id]);
        $owner_id = $stmt->fetchColumn();
        
        if ($owner_id && ($owner_id == $user['id'] || isAdmin($user))) {
            try {
                $pdo->prepare("DELETE FROM curtidas WHERE postagem_id = ?")->execute([$post_id]);
                $pdo->prepare("DELETE FROM curtidas WHERE resposta_id IN (SELECT id FROM respostas WHERE postagem_id = ?)")->execute([$post_id]);
                $pdo->prepare("DELETE FROM respostas WHERE postagem_id = ?")->execute([$post_id]);
                
                $stmt = $pdo->prepare("DELETE FROM postagens WHERE id = ?");
                $stmt->execute([$post_id]);
                header('Location: forum.php?msg=deleted');
                exit;
            } catch (PDOException $e) {
                // Ignore silent constraints
            }
        }
    } elseif ($_POST['action'] === 'like_post') {
        $post_id = intval($_POST['post_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT user_id FROM postagens WHERE id = ?");
        $stmt->execute([$post_id]);
        $owner_id = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT id FROM curtidas WHERE postagem_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user['id']]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM curtidas WHERE postagem_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user['id']]);
            if ($owner_id && $owner_id != $user['id']) {
                $pdo->prepare("UPDATE usuarios SET xp = GREATEST(0, xp - 2) WHERE id = ?")->execute([$owner_id]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO curtidas (postagem_id, user_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $user['id']]);
            if ($owner_id && $owner_id != $user['id']) {
                $pdo->prepare("UPDATE usuarios SET xp = xp + 2 WHERE id = ?")->execute([$owner_id]);
            }
        }
        header('Location: forum.php');
        exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'Postagem criada com sucesso!';
    if ($_GET['msg'] === 'deleted') $success = 'Postagem excluída com sucesso!';
}

// Buscar postagens
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where = '';
$params = [];
if (!empty($search)) {
    $where = "WHERE p.titulo LIKE ? OR p.conteudo LIKE ? OR u.nome LIKE ? OR u.nome_usuario LIKE ?";
    $searchTerm = '%' . $search . '%';
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Contar total
$countSql = "SELECT COUNT(*) FROM postagens p JOIN usuarios u ON p.user_id = u.id $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalPosts = $stmt->fetchColumn();
$totalPages = ceil($totalPosts / $per_page);

// Buscar postagens com contadores
$sql = "SELECT p.*, u.nome, u.nome_usuario, u.foto, u.role, u.xp,
        (SELECT COUNT(*) FROM respostas WHERE postagem_id = p.id) as total_respostas,
        (SELECT COUNT(*) FROM curtidas WHERE postagem_id = p.id) as total_curtidas,
        (SELECT COUNT(*) FROM curtidas WHERE postagem_id = p.id AND user_id = ?) as user_curtiu
        FROM postagens p 
        JOIN usuarios u ON p.user_id = u.id 
        $where 
        ORDER BY p.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$allParams = array_merge([$user['id']], $params);
$stmt = $pdo->prepare($sql);
$stmt->execute($allParams);
$posts = $stmt->fetchAll();

$userFoto = $user['foto'] !== 'default.png' && file_exists('uploads/' . $user['foto']) 
    ? 'uploads/' . $user['foto'] 
    : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($user['nome']) . '&backgroundColor=6c5ce7';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SGDC Forum - Discussões e compartilhamento de ideias">
    <title>Fórum - SGDC Forum</title>
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
                <li><a href="forum.php" class="active" id="navForum">🏠 Fórum</a></li>
                <li><a href="members.php" id="navMembers">👥 Membros</a></li>
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
        <!-- Page Header -->
        <div class="page-header">
            <h1>🏠 Feed do <span>Fórum</span></h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= sanitize($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Buscar postagens, usuários..." 
                       value="<?= sanitize($search) ?>" id="searchInput">
            </form>
        </div>

        <!-- New Post Form -->
        <div class="new-post-card" id="newPostCard">
            <h2>✏️ Nova Postagem</h2>
            <form method="POST" action="" id="postForm">
                <input type="hidden" name="action" value="create_post">
                <div class="form-group">
                    <input type="text" name="titulo" placeholder="Título da postagem..." required id="postTitle">
                </div>
                <div class="form-group">
                    <textarea name="conteudo" placeholder="O que você está pensando? Compartilhe com a comunidade..." required rows="4" id="postContent"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" id="btnPublish">📤 Publicar</button>
                </div>
            </form>
        </div>

        <!-- Posts List -->
        <div class="post-list">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <h3>Nenhuma postagem encontrada</h3>
                    <p>Seja o primeiro a compartilhar algo com a comunidade!</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php 
                    $postFoto = $post['foto'] !== 'default.png' && file_exists('uploads/' . $post['foto'])
                        ? 'uploads/' . $post['foto']
                        : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($post['nome']) . '&backgroundColor=6c5ce7';
                    ?>
                    <div class="post-card" id="post-<?= $post['id'] ?>">
                        <div class="post-header">
                            <a href="profile.php?id=<?= $post['user_id'] ?>">
                                <img src="<?= $postFoto ?>" alt="<?= sanitize($post['nome']) ?>" class="post-avatar">
                            </a>
                            <div class="post-meta">
                                <div class="post-author">
                                    <a href="profile.php?id=<?= $post['user_id'] ?>"><?= sanitize($post['nome']) ?></a>
                                    <?php if ($post['role'] === 'admin'): ?>
                                        <span class="badge badge-admin" style="margin-left:8px;">🛡️ ADMIN</span>
                                    <?php else: ?>
                                        <?php $level = getXPLevel($post['xp']); ?>
                                        <span class="badge badge-rank-<?= $level ?>" style="margin-left:8px;" title="Nível <?= $level ?> · <?= $post['xp'] ?> XP"><?= getRankName($level) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="post-username">@<?= sanitize($post['nome_usuario']) ?></span>
                                <span class="post-time"> · <?= timeAgo($post['created_at']) ?></span>
                            </div>
                            <?php if ($post['user_id'] == $user['id'] || isAdmin($user)): ?>
                                <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Tem certeza que deseja excluir esta postagem?')">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                    <button type="submit" class="btn-icon" data-tooltip="Excluir" title="Excluir postagem">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <a href="post.php?id=<?= $post['id'] ?>" style="text-decoration:none;">
                            <h3 class="post-title"><?= sanitize($post['titulo']) ?></h3>
                        </a>
                        
                        <div class="post-content">
                            <?= nl2br(sanitize(mb_substr($post['conteudo'], 0, 300))) ?>
                            <?php if (mb_strlen($post['conteudo']) > 300): ?>
                                <a href="post.php?id=<?= $post['id'] ?>">...ver mais</a>
                            <?php endif; ?>
                        </div>

                        <div class="post-footer">
                            <form method="POST" action="" style="margin:0;display:inline;">
                                <input type="hidden" name="action" value="like_post">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit" class="post-action <?= $post['user_curtiu'] ? 'liked' : '' ?>">
                                    <?= $post['user_curtiu'] ? '❤️' : '🤍' ?>
                                    <span class="count"><?= $post['total_curtidas'] ?></span>
                                </button>
                            </form>
                            <a href="post.php?id=<?= $post['id'] ?>" class="post-action">
                                💬 <span class="count"><?= $post['total_respostas'] ?></span> resposta<?= $post['total_respostas'] != 1 ? 's' : '' ?>
                            </a>
                            <span class="post-action" style="cursor:default;" title="Visualizações">
                                👁️ <span class="count"><?= $post['views'] ?? 0 ?></span>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">← Anterior</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Próxima →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide success alerts after 4 seconds
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
