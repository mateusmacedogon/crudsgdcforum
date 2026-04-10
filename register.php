<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: forum.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    // Validações
    if (empty($nome) || empty($email) || empty($telefone) || empty($nome_usuario) || empty($data_nascimento) || empty($senha)) {
        $error = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido.';
    } elseif (strlen($senha) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $error = 'As senhas não coincidem.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nome_usuario)) {
        $error = 'O nome de usuário deve conter apenas letras, números e underscore.';
    } else {
        // Verificar email e username duplicados
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Este e-mail já está cadastrado.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome_usuario = ?");
            $stmt->execute([$nome_usuario]);
            if ($stmt->fetch()) {
                $error = 'Este nome de usuário já está em uso.';
            } else {
                // Upload de foto
                $foto = 'default.png';
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($_FILES['foto']['type'], $allowedTypes)) {
                        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                        $foto = 'user_' . time() . '_' . uniqid() . '.' . $ext;
                        move_uploaded_file($_FILES['foto']['tmp_name'], 'uploads/' . $foto);
                    } else {
                        $error = 'Formato de imagem não suportado. Use JPEG, PNG, GIF ou WebP.';
                    }
                }

                if (empty($error)) {
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, nome_usuario, data_nascimento, foto, senha) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $email, $telefone, $nome_usuario, $data_nascimento, $foto, $senhaHash]);
                    $success = 'Conta criada com sucesso! Faça login para continuar.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cadastre-se no SGDC Forum e participe das discussões">
    <title>Cadastro - SGDC Forum</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box" style="max-width: 540px;">
            <div class="auth-logo">
                <span class="logo-icon">✨</span>
                <h1>SGDC Forum</h1>
                <p>Crie sua conta e participe da comunidade</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= sanitize($success) ?></div>
                <div style="text-align: center; margin-top: 16px;">
                    <a href="index.php" class="btn btn-primary">🔓 Ir para Login</a>
                </div>
            <?php else: ?>

            <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
                <div class="form-group">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" placeholder="Seu nome completo" required
                           value="<?= sanitize($_POST['nome'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">E-mail *</label>
                        <input type="email" id="email" name="email" placeholder="seu@email.com" required
                               value="<?= sanitize($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone *</label>
                        <input type="tel" id="telefone" name="telefone" placeholder="(00) 00000-0000" required
                               value="<?= sanitize($_POST['telefone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nome_usuario">Nome de Usuário *</label>
                        <input type="text" id="nome_usuario" name="nome_usuario" placeholder="seu_usuario" required
                               value="<?= sanitize($_POST['nome_usuario'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="data_nascimento">Data de Nascimento *</label>
                        <input type="date" id="data_nascimento" name="data_nascimento" required
                               value="<?= sanitize($_POST['data_nascimento'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Foto de Perfil</label>
                    <div class="file-upload">
                        <input type="file" id="foto" name="foto" accept="image/*">
                        <div class="file-upload-label" id="fileLabel">
                            📷 Clique para selecionar uma foto
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">Senha *</label>
                        <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha *</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="btnRegister">
                    ✨ Criar Conta
                </button>
            </form>

            <?php endif; ?>

            <div class="auth-footer">
                <p>Já tem uma conta? <a href="index.php">Faça login</a></p>
            </div>
        </div>
    </div>

    <script>
        // File input label update
        document.getElementById('foto')?.addEventListener('change', function() {
            const label = document.getElementById('fileLabel');
            if (this.files.length > 0) {
                label.innerHTML = '✅ ' + this.files[0].name;
            } else {
                label.innerHTML = '📷 Clique para selecionar uma foto';
            }
        });

        // Phone mask
        document.getElementById('telefone')?.addEventListener('input', function(e) {
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

        // Form submit animation
        document.getElementById('registerForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('btnRegister');
            btn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;margin:0"></span> Criando...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
