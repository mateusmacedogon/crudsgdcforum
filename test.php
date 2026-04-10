<?php
require 'config.php';
try {
    $pdo->exec('DELETE FROM usuarios WHERE id = 99999999');
    echo 'worked';
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
