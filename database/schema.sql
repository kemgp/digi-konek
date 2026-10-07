CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('retailer','supplier','administrator') NOT NULL,
 active BOOLEAN NOT NULL DEFAULT 1,
 session_version INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE suppliers (
 user_id INT UNSIGNED PRIMARY KEY,
 business_name VARCHAR(160) NOT NULL,
 verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
 review_note VARCHAR(500) NOT NULL DEFAULT '',
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE retailers (
 user_id INT UNSIGNED PRIMARY KEY,
 business_name VARCHAR(160) NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE administrators (
 user_id INT UNSIGNED PRIMARY KEY,
 business_name VARCHAR(160) NOT NULL DEFAULT '',
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE) ENGINE=InnoDB;
CREATE TABLE brands (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE) ENGINE=InnoDB;
CREATE TABLE products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 supplier_id INT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NOT NULL,
 brand_id INT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL,
 sku VARCHAR(80) NOT NULL,
 description TEXT NOT NULL,
 price_cents INT UNSIGNED NOT NULL,
 minimum_quantity INT UNSIGNED NOT NULL DEFAULT 1,
 active BOOLEAN NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY supplier_sku (supplier_id, sku),
 FOREIGN KEY (supplier_id) REFERENCES suppliers(user_id),
 FOREIGN KEY (category_id) REFERENCES categories(id),
 FOREIGN KEY (brand_id) REFERENCES brands(id),
 CHECK (price_cents > 0), CHECK (minimum_quantity > 0)
) ENGINE=InnoDB;
CREATE TABLE inventory (
 product_id INT UNSIGNED PRIMARY KEY,
 stock INT UNSIGNED NOT NULL DEFAULT 0,
 low_stock_notified BOOLEAN NOT NULL DEFAULT 0,
 FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;
CREATE TABLE carts (
 user_id INT UNSIGNED PRIMARY KEY,
 FOREIGN KEY (user_id) REFERENCES retailers(user_id)
) ENGINE=InnoDB;
CREATE TABLE cart_items (
 user_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 PRIMARY KEY (user_id, product_id),
 FOREIGN KEY (user_id) REFERENCES carts(user_id),
 FOREIGN KEY (product_id) REFERENCES products(id),
 CHECK (quantity > 0)
) ENGINE=InnoDB;
CREATE TABLE orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 retailer_id INT UNSIGNED NOT NULL,
 supplier_id INT UNSIGNED NOT NULL,
 status ENUM('pending','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
 total_cents BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (retailer_id) REFERENCES retailers(user_id),
 FOREIGN KEY (supplier_id) REFERENCES suppliers(user_id),
 INDEX retailer_date (retailer_id, created_at), INDEX supplier_date (supplier_id, created_at)
) ENGINE=InnoDB;
CREATE TABLE order_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 product_name VARCHAR(160) NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 price_cents INT UNSIGNED NOT NULL,
 FOREIGN KEY (order_id) REFERENCES orders(id),
 FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;
CREATE TABLE supplier_documents (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 supplier_id INT UNSIGNED NOT NULL,
 document_type VARCHAR(80) NOT NULL DEFAULT 'Business registration',
 original_name VARCHAR(190) NOT NULL,
 stored_name VARCHAR(64) NOT NULL UNIQUE,
 mime_type VARCHAR(80) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (supplier_id) REFERENCES suppliers(user_id)
) ENGINE=InnoDB;
CREATE TABLE notifications (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 message VARCHAR(500) NOT NULL,
 read_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id), INDEX user_notifications (user_id, created_at)
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 action VARCHAR(80) NOT NULL,
 detail VARCHAR(500) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE login_attempts (
 identity_hash CHAR(64) PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL,
 last_attempt DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_reviews (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 retailer_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 order_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 comment VARCHAR(2000) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY reviewer_product (retailer_id, product_id),
 FOREIGN KEY (retailer_id) REFERENCES retailers(user_id),
 FOREIGN KEY (product_id) REFERENCES products(id),
 FOREIGN KEY (order_id) REFERENCES orders(id),
 CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS seller_reviews (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 retailer_id INT UNSIGNED NOT NULL,
 supplier_id INT UNSIGNED NOT NULL,
 order_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 comment VARCHAR(2000) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY reviewer_supplier (retailer_id, supplier_id),
 FOREIGN KEY (retailer_id) REFERENCES retailers(user_id),
 FOREIGN KEY (supplier_id) REFERENCES suppliers(user_id),
 FOREIGN KEY (order_id) REFERENCES orders(id),
 CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_settings (
 user_id INT UNSIGNED PRIMARY KEY,
 preferences TEXT NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS marketplace_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 required_documents TEXT NOT NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO marketplace_settings (id,required_documents) VALUES (1,'["Business registration"]');
