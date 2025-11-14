CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','processing','shipped','cancelled') NOT NULL DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('insert','update','delete') NOT NULL,
    table_name VARCHAR(120) NOT NULL,
    row_id BIGINT UNSIGNED NOT NULL,
    diff JSON NULL,
    actor VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_events_created_at (created_at),
    INDEX idx_events_table_row (table_name, row_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email) VALUES
('Ada Lovelace', 'ada@example.com'),
('Grace Hopper', 'grace@example.com'),
('Leslie Lamport', 'leslie@example.com')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO orders (user_id, status, amount, updated_at) VALUES
(1, 'pending', 24.90, NOW()),
(2, 'processing', 59.99, NOW()),
(3, 'shipped', 129.00, NOW())
ON DUPLICATE KEY UPDATE status = VALUES(status), amount = VALUES(amount), updated_at = VALUES(updated_at);
