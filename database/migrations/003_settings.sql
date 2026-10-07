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
