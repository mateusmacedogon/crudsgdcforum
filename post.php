<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);
$error = '';
$success = '';

$post_id = intval($_GET['id'] ?? 0);
if ($post_id <= 0) {
    header('Location: forum.php');
    exit;
}

// Buscar postagem
$stmt = $pdo->prepare("SELECT p.*, u.nome, u.nome_usuario, u.foto, u.role, u.xp,
    (SELECT COUNT(*) FROM curtidas WHERE postagem_id = p.id) as total_curtidas,
    (SELECT COUNT(*) FROM curtidas WHERE postagem_id = p.id AND user_id = ?) as user_curtiu
    FROM postagens p JOIN usuarios u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$user['id'], $post_id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: forum.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pdo->prepare("UPDATE postagens SET views = views + 1 WHERE id = ?")->execute([$post_id]);
    $post['views'] = ($post['views'] ?? 0) + 1;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reply') {
        $conteudo = trim($_POST['conteudo'] ?? '');
        if (empty($conteudo)) {
            $error = 'Escreva algo para responder.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO respostas (postagem_id, user_id, conteudo) VALUES (?, ?, ?)");
            $stmt->execute([$post_id, $user['id'], $conteudo]);
            // XP da resposta
            $pdo->prepare("UPDATE usuarios SET xp = xp + 5 WHERE id = ?")->execute([$user['id']]);
            header('Location: post.php?id=' . $post_id . '&msg=replied');
            exit;
        }
    } elseif ($_POST['action'] === 'delete_reply') {
        $reply_id = intval($_POST['reply_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT user_id FROM respostas WHERE id = ?");
        $stmt->execute([$reply_id]);
        $owner_id = $stmt->fetchColumn();
        if ($owner_id && ($owner_id == $user['id'] || isAdmin($user))) {
            try {
                $pdo->prepare("DELETE FROM curtidas WHERE resposta_id = ?")->execute([$reply_id]);
                $stmt = $pdo->prepare("DELETE FROM respostas WHERE id = ?");
                $stmt->execute([$reply_id]);
                header('Location: post.php?id=' . $post_id . '&msg=reply_deleted');
                exit;
            } catch (PDOException $e) {
                // Ignore silent constraints
            }
        }
    } elseif ($_POST['action'] === 'like_post') {
        $stmt = $pdo->prepare("SELECT id FROM curtidas WHERE postagem_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user['id']]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM curtidas WHERE postagem_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user['id']]);
            if ($post['user_id'] != $user['id']) {
                 $pdo->prepare("UPDATE usuarios SET xp = GREATEST(0, xp - 2) WHERE id = ?")->execute([$post['user_id']]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO curtidas (postagem_id, user_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $user['id']]);
            if ($post['user_id'] != $user['id']) {
                 $pdo->prepare("UPDATE usuarios SET xp = xp + 2 WHERE id = ?")->execute([$post['user_id']]);
            }
        }
        header('Location: post.php?id=' . $post_id);
        exit;
    } elseif ($_POST['action'] === 'edit_post' && $post['user_id'] == $user['id']) {
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');
        if (!empty($titulo) && !empty($conteudo)) {
            $stmt = $pdo->prepare("UPDATE postagens SET titulo = ?, conteudo = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$titulo, $conteudo, $post_id, $user['id']]);
            header('Location: post.php?id=' . $post_id . '&msg=edited');
            exit;
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'replied') $success = 'Resposta enviada com sucesso!';
    if ($_GET['msg'] === 'reply_deleted') $success = 'Resposta excluída!';
    if ($_GET['msg'] === 'edited') $success = 'Postagem editada com sucesso!';
}

// Recarregar post após ações
$stmt = $pdo->prepare("SELECT p.*, u.nome, u.nome_usuario, u.foto, u.role, u.xp,
    (SELECT COUNT(*) FROM curtidas WHERE postagem_id = p.id) as total_curtidas,
    (SELECT COUNT(*) FROM curtidas WHERE postagem_id = p.id AND user_id = ?) as user_curtiu
    FROM postagens p JOIN usuarios u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$user['id'], $post_id]);
$post = $stmt->fetch();

// Buscar respostas
$stmt = $pdo->prepare("SELECT r.*, u.nome, u.nome_usuario, u.foto, u.role, u.xp 
    FROM respostas r JOIN usuarios u ON r.user_id = u.id 
    WHERE r.postagem_id = ? ORDER BY r.created_at ASC");
$stmt->execute([$post_id]);
$replies = $stmt->fetchAll();

$postFoto = $post['foto'] !== 'default.png' && file_exists('uploads/' . $post['foto'])
    ? 'uploads/' . $post['foto']
    : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($post['nome']) . '&backgroundColor=6c5ce7';

$userFoto = $user['foto'] !== 'default.png' && file_exists('uploads/' . $user['foto'])
    ? 'uploads/' . $user['foto']
    : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($user['nome']) . '&backgroundColor=6c5ce7';

$editing = isset($_GET['edit']) && $post['user_id'] == $user['id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize(mb_substr($post['conteudo'], 0, 160)) ?>">
    <title><?= sanitize($post['titulo']) ?> - SGDC Forum</title>
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
        <!-- Breadcrumb -->
        <div style="margin-bottom: 20px;">
            <a href="forum.php" style="color: var(--text-secondary); font-size: 0.9rem;">← Voltar ao Fórum</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= sanitize($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
        <?php endif; ?>

        <!-- Post Detail -->
        <div class="post-detail">
            <div class="post-header">
                <a href="profile.php?id=<?= $post['user_id'] ?>">
                    <img src="<?= $postFoto ?>" alt="<?= sanitize($post['nome']) ?>" class="post-avatar">
                </a>
                <div class="post-meta">
                    <div class="post-author">
                        <a href="profile.php?id=<?= $post['user_id'] ?>"><?= sanitize($post['nome']) ?></a>
                        <?php if ($post['user_id'] == $user['id']): ?>
                            <span class="badge badge-primary" style="margin-left:8px;">Autor</span>
                        <?php endif; ?>
                        <?php if ($post['role'] === 'admin'): ?>
                            <span class="badge badge-admin" style="margin-left:8px;">🛡️ ADMIN</span>
                        <?php else: ?>
                            <?php $p_level = getXPLevel($post['xp']); ?>
                            <span class="badge badge-rank-<?= $p_level ?>" style="margin-left:8px;" title="Nível <?= $p_level ?> · <?= $post['xp'] ?> XP"><?= getRankName($p_level) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="post-username">@<?= sanitize($post['nome_usuario']) ?></span>
                    <span class="post-time"> · <?= timeAgo($post['created_at']) ?></span>
                    <?php if ($post['updated_at'] !== $post['created_at']): ?>
                        <span class="post-time"> · (editado)</span>
                    <?php endif; ?>
                </div>
                <?php if ($post['user_id'] == $user['id'] || isAdmin($user)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="?id=<?= $post_id ?>&edit=1" class="btn-icon" title="Editar">✏️</a>
                        <form method="POST" action="forum.php" style="margin:0;" onsubmit="return confirm('Tem certeza?')">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= $post_id ?>">
                            <button type="submit" class="btn-icon" title="Excluir">🗑️</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($editing): ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_post">
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" name="titulo" value="<?= sanitize($post['titulo']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Conteúdo</label>
                        <textarea name="conteudo" rows="6" required><?= sanitize($post['conteudo']) ?></textarea>
                    </div>
                    <div style="display:flex;gap:12px;justify-content:flex-end;">
                        <a href="?id=<?= $post_id ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="post-title" style="font-size:1.5rem;margin-bottom:16px;"><?= sanitize($post['titulo']) ?></h2>
                <div class="post-content">
                    <?= nl2br(sanitize($post['conteudo'])) ?>
                </div>
            <?php endif; ?>

            <div class="post-footer">
                <form method="POST" action="" style="margin:0;display:inline;">
                    <input type="hidden" name="action" value="like_post">
                    <button type="submit" class="post-action <?= $post['user_curtiu'] ? 'liked' : '' ?>">
                        <?= $post['user_curtiu'] ? '❤️' : '🤍' ?>
                        <span class="count"><?= $post['total_curtidas'] ?></span> curtida<?= $post['total_curtidas'] != 1 ? 's' : '' ?>
                    </button>
                </form>
                <span class="post-action" style="cursor:default;">
                    💬 <span class="count"><?= count($replies) ?></span> resposta<?= count($replies) != 1 ? 's' : '' ?>
                </span>
                <span class="post-action" style="cursor:default;" title="Visualizações">
                    👁️ <span class="count"><?= $post['views'] ?? 0 ?></span>
                </span>
            </div>
        </div>

        <!-- Replies Section -->
        <div class="replies-section">
            <div class="replies-header">
                💬 Respostas <span class="count"><?= count($replies) ?></span>
            </div>

            <?php if (empty($replies)): ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <div class="icon">💭</div>
                    <h3>Nenhuma resposta ainda</h3>
                    <p>Seja o primeiro a responder a esta discussão!</p>
                </div>
            <?php else: ?>
                <?php foreach ($replies as $index => $reply): ?>
                    <?php 
                    $replyFoto = $reply['foto'] !== 'default.png' && file_exists('uploads/' . $reply['foto'])
                        ? 'uploads/' . $reply['foto']
                        : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($reply['nome']) . '&backgroundColor=6c5ce7';
                    ?>
                    <div class="reply-card" id="reply-<?= $reply['id'] ?>" style="animation-delay: <?= $index * 0.05 ?>s;">
                        <div class="post-header">
                            <a href="profile.php?id=<?= $reply['user_id'] ?>">
                                <img src="<?= $replyFoto ?>" alt="<?= sanitize($reply['nome']) ?>" class="post-avatar" style="width:36px;height:36px;">
                            </a>
                            <div class="post-meta">
                                <div class="post-author" style="font-size:0.9rem;">
                                    <a href="profile.php?id=<?= $reply['user_id'] ?>"><?= sanitize($reply['nome']) ?></a>
                                    <?php if ($reply['user_id'] == $post['user_id']): ?>
                                        <span class="badge badge-primary" style="margin-left:6px;font-size:0.65rem;">Autor</span>
                                    <?php endif; ?>
                                    <?php if ($reply['role'] === 'admin'): ?>
                                        <span class="badge badge-admin" style="margin-left:6px;font-size:0.65rem;">🛡️ ADMIN</span>
                                    <?php else: ?>
                                        <?php $r_level = getXPLevel($reply['xp']); ?>
                                        <span class="badge badge-rank-<?= $r_level ?>" style="margin-left:6px;font-size:0.65rem;" title="Nível <?= $r_level ?> · <?= $reply['xp'] ?> XP"><?= getRankName($r_level) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="post-username">@<?= sanitize($reply['nome_usuario']) ?></span>
                                <span class="post-time"> · <?= timeAgo($reply['created_at']) ?></span>
                            </div>
                            <?php if ($reply['user_id'] == $user['id'] || isAdmin($user)): ?>
                                <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Excluir esta resposta?')">
                                    <input type="hidden" name="action" value="delete_reply">
                                    <input type="hidden" name="reply_id" value="<?= $reply['id'] ?>">
                                    <button type="submit" class="btn-icon" style="font-size:0.8rem;" title="Excluir">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="post-content" style="margin-left:48px;margin-top:4px;">
                            <?= nl2br(sanitize($reply['conteudo'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Reply Form -->
            <div class="reply-form">
                <h3>✍️ Escrever Resposta</h3>
                <form method="POST" action="" id="replyForm">
                    <input type="hidden" name="action" value="reply">
                    <div class="form-group">
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <img src="<?= $userFoto ?>" alt="Você" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);flex-shrink:0;margin-top:4px;">
                            <textarea name="conteudo" placeholder="Escreva sua resposta..." required rows="3" id="replyContent" style="flex:1;"></textarea>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary" id="btnReply">📤 Responder</button>
                    </div>
                </form>
            </div>
        </div>
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

        // Scroll to reply form hash
        if (window.location.hash === '#reply') {
            document.getElementById('replyContent')?.focus();
        }
    </script>
</body>
</html>
