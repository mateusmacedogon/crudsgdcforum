<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Criar banco de dados
    $pdo->exec("CREATE DATABASE IF NOT EXISTS sgdc_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE sgdc_forum");

    // Tabela de usuários
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        telefone VARCHAR(20) NOT NULL,
        nome_usuario VARCHAR(50) NOT NULL UNIQUE,
        data_nascimento DATE NOT NULL,
        foto VARCHAR(255) DEFAULT 'default.png',
        senha VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        xp INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Adicionar colunas se por acaso a tabela já existir no formato antigo
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN xp INT DEFAULT 0"); } catch (Exception $e) {}

    // Inserir Admin Automático
    $adminEmail = 'sociedadegdc@gmail.com';
    $adminSenha = 'gloriaagdc'; // Em um sistema real a senha poderia vir por interface, fixo para o escopo
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $hash = password_hash($adminSenha, PASSWORD_DEFAULT);
        $stmtAdmin = $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, nome_usuario, data_nascimento, senha, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtAdmin->execute(['Admin', $adminEmail, '0000000000', 'admin_sgdc', date('Y-m-d'), $hash, 'admin']);
    }

    // Tabela de postagens do fórum
    $pdo->exec("CREATE TABLE IF NOT EXISTS postagens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        conteudo TEXT NOT NULL,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    try { $pdo->exec("ALTER TABLE postagens ADD COLUMN views INT DEFAULT 0"); } catch (Exception $e) {}

    // Tabela de respostas
    $pdo->exec("CREATE TABLE IF NOT EXISTS respostas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        postagem_id INT NOT NULL,
        user_id INT NOT NULL,
        conteudo TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (postagem_id) REFERENCES postagens(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Tabela de curtidas
    $pdo->exec("CREATE TABLE IF NOT EXISTS curtidas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        postagem_id INT DEFAULT NULL,
        resposta_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (postagem_id) REFERENCES postagens(id) ON DELETE CASCADE,
        FOREIGN KEY (resposta_id) REFERENCES respostas(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_post_like (postagem_id, user_id),
        UNIQUE KEY unique_reply_like (resposta_id, user_id)
    ) ENGINE=InnoDB");

    // Criar pasta de uploads
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }

    echo '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Setup - SGDC Forum</title>
        <style>
            body { 
                background: #0a0a1a; 
                color: #fff; 
                font-family: "Inter", sans-serif; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
                margin: 0;
            }
            .box { 
                background: rgba(255,255,255,0.05); 
                border: 1px solid rgba(255,255,255,0.1); 
                border-radius: 16px; 
                padding: 40px; 
                text-align: center; 
                backdrop-filter: blur(10px);
            }
            .box h1 { color: #6c5ce7; margin-bottom: 10px; }
            .box p { color: #b2bec3; margin: 8px 0; }
            .box a { 
                display: inline-block; 
                margin-top: 20px; 
                background: linear-gradient(135deg, #6c5ce7, #a29bfe); 
                color: #fff; 
                padding: 12px 32px; 
                border-radius: 8px; 
                text-decoration: none; 
                font-weight: 600;
                transition: transform 0.2s;
            }
            .box a:hover { transform: translateY(-2px); }
            .check { color: #00b894; font-size: 1.2em; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>✅ Setup Completo!</h1>
            <p class="check">✔ Banco de dados <strong>sgdc_forum</strong> criado</p>
            <p class="check">✔ Tabela <strong>usuarios</strong> criada</p>
            <p class="check">✔ Tabela <strong>postagens</strong> criada</p>
            <p class="check">✔ Tabela <strong>respostas</strong> criada</p>
            <p class="check">✔ Tabela <strong>curtidas</strong> criada</p>
            <p class="check">✔ Pasta <strong>uploads</strong> criada</p>
            <a href="index.php">Ir para o SGDC Forum →</a>
        </div>
    </body>
    </html>';

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
