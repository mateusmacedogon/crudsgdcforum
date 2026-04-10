<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);
$error = '';
$success = '';

// Determinar se é o perfil do próprio usuário ou de outro
$profile_id = intval($_GET['id'] ?? $user['id']);
$isOwnProfile = ($profile_id == $user['id']);

// Buscar dados do perfil
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$profile_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: forum.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user_admin' && isAdmin($user) && !$isOwnProfile) {
    if ($profile['role'] !== 'admin') {
        try {
            // Manual cascade para lidar com possíveis falhas de FK (Foreign Key sem ON DELETE CASCADE)
            $pdo->prepare("DELETE FROM curtidas WHERE postagem_id IN (SELECT id FROM postagens WHERE user_id = ?)")->execute([$profile_id]);
            $pdo->prepare("DELETE FROM curtidas WHERE resposta_id IN (SELECT id FROM respostas WHERE user_id = ?)")->execute([$profile_id]);
            $pdo->prepare("DELETE FROM respostas WHERE postagem_id IN (SELECT id FROM postagens WHERE user_id = ?)")->execute([$profile_id]);
            
            $pdo->prepare("DELETE FROM curtidas WHERE user_id = ?")->execute([$profile_id]);
            $pdo->prepare("DELETE FROM respostas WHERE user_id = ?")->execute([$profile_id]);
            $pdo->prepare("DELETE FROM postagens WHERE user_id = ?")->execute([$profile_id]);

            if ($profile['foto'] !== 'default.png' && file_exists('uploads/' . $profile['foto'])) {
                unlink('uploads/' . $profile['foto']);
            }
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$profile_id]);
            header('Location: members.php?msg=user_deleted');
            exit;
        } catch (PDOException $e) {
            $error = 'Erro de banco de dados ao banir: ' . $e->getMessage();
        }
    } else {
        $error = 'Você não pode excluir outro administrador.';
    }
}

// Processar edição do perfil (apenas do próprio)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update') {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $nome_usuario = trim($_POST['nome_usuario'] ?? '');
            $data_nascimento = $_POST['data_nascimento'] ?? '';

            if (empty($nome) || empty($email) || empty($telefone) || empty($nome_usuario) || empty($data_nascimento)) {
                $error = 'Preencha todos os campos obrigatórios.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'E-mail inválido.';
            } else {
                // Verificar duplicatas (excluindo o próprio usuário)
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user['id']]);
                if ($stmt->fetch()) {
                    $error = 'Este e-mail já está em uso por outro usuário.';
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome_usuario = ? AND id != ?");
                    $stmt->execute([$nome_usuario, $user['id']]);
                    if ($stmt->fetch()) {
                        $error = 'Este nome de usuário já está em uso.';
                    } else {
                        // Upload de nova foto
                        $fotoUpdate = '';
                        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            if (in_array($_FILES['foto']['type'], $allowedTypes)) {
                                // Excluir foto antiga
                                if ($user['foto'] !== 'default.png' && file_exists('uploads/' . $user['foto'])) {
                                    unlink('uploads/' . $user['foto']);
                                }
                                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                                $foto = 'user_' . time() . '_' . uniqid() . '.' . $ext;
                                move_uploaded_file($_FILES['foto']['tmp_name'], 'uploads/' . $foto);
                                $fotoUpdate = ", foto = ?";
                            } else {
                                $error = 'Formato de imagem não suportado.';
                            }
                        }

                        if (empty($error)) {
                            if ($fotoUpdate) {
                                $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ?, nome_usuario = ?, data_nascimento = ? $fotoUpdate WHERE id = ?");
                                $stmt->execute([$nome, $email, $telefone, $nome_usuario, $data_nascimento, $foto, $user['id']]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ?, nome_usuario = ?, data_nascimento = ? WHERE id = ?");
                                $stmt->execute([$nome, $email, $telefone, $nome_usuario, $data_nascimento, $user['id']]);
                            }
                            header('Location: profile.php?msg=updated');
                            exit;
                        }
                    }
                }
            }
        } elseif ($_POST['action'] === 'change_password') {
            $senha_atual = $_POST['senha_atual'] ?? '';
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';

            if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
                $error = 'Preencha todos os campos de senha.';
            } elseif (!password_verify($senha_atual, $user['senha'])) {
                $error = 'Senha atual incorreta.';
            } elseif (strlen($nova_senha) < 6) {
                $error = 'A nova senha deve ter pelo menos 6 caracteres.';
            } elseif ($nova_senha !== $confirmar_senha) {
                $error = 'As senhas não coincidem.';
            } else {
                $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->execute([$senhaHash, $user['id']]);
                header('Location: profile.php?msg=password_changed');
                exit;
            }
        } elseif ($_POST['action'] === 'delete_account') {
            $senha_confirmar = $_POST['senha_confirmar'] ?? '';
            if (password_verify($senha_confirmar, $user['senha'])) {
                try {
                    $u_id = $user['id'];
                    $pdo->prepare("DELETE FROM curtidas WHERE postagem_id IN (SELECT id FROM postagens WHERE user_id = ?)")->execute([$u_id]);
                    $pdo->prepare("DELETE FROM curtidas WHERE resposta_id IN (SELECT id FROM respostas WHERE user_id = ?)")->execute([$u_id]);
                    $pdo->prepare("DELETE FROM respostas WHERE postagem_id IN (SELECT id FROM postagens WHERE user_id = ?)")->execute([$u_id]);
                    
                    $pdo->prepare("DELETE FROM curtidas WHERE user_id = ?")->execute([$u_id]);
                    $pdo->prepare("DELETE FROM respostas WHERE user_id = ?")->execute([$u_id]);
                    $pdo->prepare("DELETE FROM postagens WHERE user_id = ?")->execute([$u_id]);

                    if ($user['foto'] !== 'default.png' && file_exists('uploads/' . $user['foto'])) {
                        unlink('uploads/' . $user['foto']);
                    }
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt->execute([$u_id]);
                    session_destroy();
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Erro de banco de dados ao excluir conta: ' . $e->getMessage();
                }
            } else {
                $error = 'Senha incorreta. Conta não foi excluída.';
            }
        }
    }
}

// Recarregar dados após possível update
if ($isOwnProfile) {
    $user = getCurrentUser($pdo);
    $profile = $user;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $success = 'Perfil atualizado com sucesso!';
    if ($_GET['msg'] === 'password_changed') $success = 'Senha alterada com sucesso!';
}

// Estatísticas do perfil
$stmt = $pdo->prepare("SELECT COUNT(*) FROM postagens WHERE user_id = ?");
$stmt->execute([$profile_id]);
$totalPosts = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM respostas WHERE user_id = ?");
$stmt->execute([$profile_id]);
$totalReplies = $stmt->fetchColumn();

// Postagens recentes do perfil
$stmt = $pdo->prepare("SELECT p.*, 
    (SELECT COUNT(*) FROM respostas WHERE postagem_id = p.id) as total_respostas,
    (SELECT COUNT(*) FROM curtidas WHERE postagem_id = p.id) as total_curtidas
    FROM postagens p WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 5");
$stmt->execute([$profile_id]);
$recentPosts = $stmt->fetchAll();

$profileFoto = $profile['foto'] !== 'default.png' && file_exists('uploads/' . $profile['foto'])
    ? 'uploads/' . $profile['foto']
    : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($profile['nome']) . '&backgroundColor=6c5ce7';

$userFoto = $user['foto'] !== 'default.png' && file_exists('uploads/' . $user['foto'])
    ? 'uploads/' . $user['foto']
    : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($user['nome']) . '&backgroundColor=6c5ce7';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Perfil de <?= sanitize($profile['nome']) ?> no SGDC Forum">
    <title><?= sanitize($profile['nome']) ?> - SGDC Forum</title>
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
                <li><a href="profile.php" class="active" id="navProfile">👤 Meu Perfil</a></li>
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
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar-container">
                    <img src="<?= $profileFoto ?>" alt="<?= sanitize($profile['nome']) ?>" class="profile-avatar">
                </div>
                <div class="profile-name"><?= sanitize($profile['nome']) ?></div>
                <div class="profile-username">@<?= sanitize($profile['nome_usuario']) ?></div>
                
                <div style="margin-bottom: 20px;">
                <?php if ($profile['role'] === 'admin'): ?>
                    <span class="badge badge-admin">🛡️ Administrador do Fórum</span>
                <?php else: ?>
                    <?php 
                    $lvl = getXPLevel($profile['xp']);
                    $rankName = getRankName($lvl);
                    $nextXP = getNextLevelXP($lvl);
                    $percent = min(100, ($profile['xp'] / $nextXP) * 100);
                    ?>
                    <span class="badge badge-rank-<?= $lvl ?>" style="margin-bottom:8px;display:inline-block;padding:6px 14px;font-size:0.8rem;">
                        🏅 Nível <?= $lvl ?> · <?= $rankName ?>
                    </span>
                    <div class="xp-container">
                        <div class="xp-header">
                            <span>⭐ <?= $profile['xp'] ?> XP</span>
                            <span>Mín. <?= $nextXP ?> XP ➡️</span>
                        </div>
                        <div class="xp-bar">
                            <div class="xp-fill" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
                </div>

                <div class="profile-stats">
                    <div class="stat-box">
                        <div class="stat-number"><?= $totalPosts ?></div>
                        <div class="stat-label">Postagens</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?= $totalReplies ?></div>
                        <div class="stat-label">Respostas</div>
                    </div>
                </div>

                <div class="profile-info">
                    <div class="profile-info-item">
                        <span class="icon">📧</span>
                        <?= sanitize($profile['email']) ?>
                    </div>
                    <div class="profile-info-item">
                        <span class="icon">📱</span>
                        <?= sanitize($profile['telefone']) ?>
                    </div>
                    <div class="profile-info-item">
                        <span class="icon">🎂</span>
                        <?= date('d/m/Y', strtotime($profile['data_nascimento'])) ?>
                    </div>
                    <div class="profile-info-item">
                        <span class="icon">📅</span>
                        Membro desde <?= date('M/Y', strtotime($profile['created_at'])) ?>
                    </div>
                </div>

                <?php if (isAdmin($user) && !$isOwnProfile && $profile['role'] !== 'admin'): ?>
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                    <form method="POST" action="" onsubmit="return confirm('ATENÇÃO: Banir este usuário apagará permanentemente todas as suas postagens e respostas. Continuar?')">
                        <input type="hidden" name="action" value="delete_user_admin">
                        <button type="submit" class="btn btn-danger btn-block" style="font-size: 0.85rem;">
                            🔨 Banir Usuário
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="profile-main">
                <?php if ($isOwnProfile): ?>
                    <!-- Tabs -->
                    <div class="tabs">
                        <button class="tab active" onclick="showTab('posts')" id="tabPosts">📝 Postagens</button>
                        <button class="tab" onclick="showTab('edit')" id="tabEdit">✏️ Editar Perfil</button>
                        <button class="tab" onclick="showTab('security')" id="tabSecurity">🔒 Segurança</button>
                    </div>
                <?php endif; ?>

                <!-- Tab: Posts -->
                <div id="tab-posts" class="tab-content">
                    <div class="profile-form-card">
                        <h2>📝 Postagens Recentes</h2>
                        <?php if (empty($recentPosts)): ?>
                            <div class="empty-state" style="padding: 30px;">
                                <div class="icon">📝</div>
                                <h3>Nenhuma postagem ainda</h3>
                                <p><?= $isOwnProfile ? 'Comece compartilhando algo no fórum!' : 'Este usuário ainda não publicou nada.' ?></p>
                                <?php if ($isOwnProfile): ?>
                                    <a href="forum.php" class="btn btn-primary btn-sm" style="margin-top:16px;">✏️ Criar Postagem</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="post-list">
                                <?php foreach ($recentPosts as $rp): ?>
                                    <div class="post-card" style="padding:18px;">
                                        <a href="post.php?id=<?= $rp['id'] ?>">
                                            <h3 class="post-title" style="font-size:1.05rem;"><?= sanitize($rp['titulo']) ?></h3>
                                        </a>
                                        <div class="post-content" style="font-size:0.85rem;margin-bottom:10px;">
                                            <?= nl2br(sanitize(mb_substr($rp['conteudo'], 0, 150))) ?>
                                            <?= mb_strlen($rp['conteudo']) > 150 ? '...' : '' ?>
                                        </div>
                                        <div class="post-footer" style="padding-top:10px;">
                                            <span class="post-action" style="cursor:default;">❤️ <?= $rp['total_curtidas'] ?></span>
                                            <span class="post-action" style="cursor:default;">💬 <?= $rp['total_respostas'] ?></span>
                                            <span class="post-time"><?= timeAgo($rp['created_at']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isOwnProfile): ?>
                <!-- Tab: Edit Profile -->
                <div id="tab-edit" class="tab-content" style="display:none;">
                    <div class="profile-form-card">
                        <h2>✏️ Editar Perfil</h2>
                        <form method="POST" action="" enctype="multipart/form-data" id="editForm">
                            <input type="hidden" name="action" value="update">
                            
                            <div class="form-group">
                                <label for="editNome">Nome Completo</label>
                                <input type="text" id="editNome" name="nome" value="<?= sanitize($profile['nome']) ?>" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editEmail">E-mail</label>
                                    <input type="email" id="editEmail" name="email" value="<?= sanitize($profile['email']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="editTelefone">Telefone</label>
                                    <input type="tel" id="editTelefone" name="telefone" value="<?= sanitize($profile['telefone']) ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editUsername">Nome de Usuário</label>
                                    <input type="text" id="editUsername" name="nome_usuario" value="<?= sanitize($profile['nome_usuario']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="editNascimento">Data de Nascimento</label>
                                    <input type="date" id="editNascimento" name="data_nascimento" value="<?= $profile['data_nascimento'] ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Alterar Foto</label>
                                <div class="file-upload">
                                    <input type="file" id="editFoto" name="foto" accept="image/*">
                                    <div class="file-upload-label" id="editFileLabel">
                                        📷 Clique para selecionar uma nova foto
                                    </div>
                                </div>
                            </div>

                            <div class="profile-actions">
                                <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Security -->
                <div id="tab-security" class="tab-content" style="display:none;">
                    <div class="profile-form-card" style="margin-bottom:24px;">
                        <h2>🔒 Alterar Senha</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label for="senhaAtual">Senha Atual</label>
                                <input type="password" id="senhaAtual" name="senha_atual" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="novaSenha">Nova Senha</label>
                                    <input type="password" id="novaSenha" name="nova_senha" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="confirmarSenha">Confirmar Nova Senha</label>
                                    <input type="password" id="confirmarSenha" name="confirmar_senha" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">🔒 Alterar Senha</button>
                        </form>
                    </div>

                    <div class="profile-form-card" style="border-color: rgba(225, 112, 85, 0.3);">
                        <h2 style="color: var(--danger);">⚠️ Zona de Perigo</h2>
                        <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 0.9rem;">
                            Excluir sua conta é uma ação permanente. Todas as suas postagens e respostas serão removidas.
                        </p>
                        <button class="btn btn-danger" onclick="document.getElementById('deleteModal').classList.add('active')" id="btnDeleteAccount">
                            🗑️ Excluir Minha Conta
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <?php if ($isOwnProfile): ?>
    <div class="delete-overlay" id="deleteModal">
        <div class="delete-box">
            <div class="icon">⚠️</div>
            <h3>Excluir Conta</h3>
            <p>Esta ação é irreversível. Digite sua senha para confirmar.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_account">
                <div class="form-group">
                    <input type="password" name="senha_confirmar" placeholder="Sua senha" required>
                </div>
                <div class="modal-actions" style="justify-content:center;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteModal').classList.remove('active')">Cancelar</button>
                    <button type="submit" class="btn btn-danger">🗑️ Excluir Permanentemente</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById('tab-' + tab).style.display = 'block';
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
        }

        // File input
        document.getElementById('editFoto')?.addEventListener('change', function() {
            const label = document.getElementById('editFileLabel');
            if (this.files.length > 0) {
                label.innerHTML = '✅ ' + this.files[0].name;
            }
        });

        // Phone mask
        document.getElementById('editTelefone')?.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 6) {
                v = '(' + v.slice(0, 2) + ') ' + v.slice(2, 7) + '-' + v.slice(7);
            } else if (v.length > 2) {
                v = '(' + v.slice(0, 2) + ') ' + v.slice(2);
            } else if (v.length > 0) {
                v = '(' + v;
            }
            e.target.value = v;
        });

        // Close modal on overlay click
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });

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
