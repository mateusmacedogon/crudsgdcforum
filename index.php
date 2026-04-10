<?php
require_once 'config.php';

// Se já está logado, redireciona para o fórum
if (isLoggedIn()) {
    header('Location: forum.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $error = 'Preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: forum.php');
            exit;
        } else {
            $error = 'E-mail ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SGDC Forum - Conecte-se com pessoas e compartilhe ideias">
    <title>Login - SGDC Forum</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <span class="logo-icon">💬</span>
                <h1>SGDC Forum</h1>
                <p>Conecte-se e compartilhe suas ideias</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" required
                           value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="btnLogin">
                    🔓 Entrar
                </button>
            </form>

            <div class="auth-footer">
                <p>Não tem uma conta? <a href="register.php">Cadastre-se</a></p>
            </div>
        </div>
    </div>

    <script>
        // Add subtle animation to the form
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnLogin');
            btn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;margin:0"></span> Entrando...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
