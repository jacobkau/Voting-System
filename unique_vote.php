<?php


header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/conn.php';

echo "=== Password Reset Rate Limiting Migration ===\n\n";

try {
    // Rate-limit log table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS password_reset_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_time (email, attempted_at),
            INDEX idx_ip_time    (ip_address, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ Table 'password_reset_attempts' ready.\n";

    // Ensure the tokens table exists with proper indexing
    $conn->exec("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            expiry DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_time (user_id, created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ Table 'password_reset_tokens' ready.\n";

    // Add user_time index if it's missing (table may already exist)
    $hasIndex = $conn->query("
        SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name   = 'password_reset_tokens'
          AND index_name   = 'idx_user_time'
    ")->fetchColumn();

    if (!$hasIndex) {
        try {
            $conn->exec("ALTER TABLE password_reset_tokens ADD INDEX idx_user_time (user_id, created_at)");
            echo "✅ Added idx_user_time to password_reset_tokens.\n";
        } catch (PDOException $e) {
            echo "ℹ️  Could not add idx_user_time: " . $e->getMessage() . "\n";
        }
    } else {
        echo "ℹ️  idx_user_time already present.\n";
    }

    echo "\n✅ Migration complete. DELETE THIS FILE NOW.\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
}
