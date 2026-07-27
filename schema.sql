-- =============================================
-- Sales System - Base Schema
-- =============================================

CREATE DATABASE IF NOT EXISTS sales_system
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sales_system;

-- ---------------------------------------------
-- Users (system accounts, not customers)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Orders
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150)   NOT NULL,
    product_name  VARCHAR(150)   NOT NULL,
    quantity      INT            NOT NULL DEFAULT 1,
    unit_price    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    total_amount  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    order_date    DATE           NOT NULL,
    status        ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_by    INT            NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_date (order_date),
    INDEX idx_orders_customer (customer_name)
) ENGINE=InnoDB;
