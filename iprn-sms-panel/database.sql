CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY,
  min_payout DECIMAL(10,2) DEFAULT 5000,
  signup_enabled TINYINT(1) DEFAULT 1,
  default_rate DECIMAL(10,4) DEFAULT 0.08,
  default_payout INT DEFAULT 70
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ranges (
  id INT PRIMARY KEY AUTO_INCREMENT,
  range_name VARCHAR(100),
  country VARCHAR(50),
  rate DECIMAL(10,4),
  payout_percent DECIMAL(5,2),
  total_stock INT,
  available_stock INT,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS numbers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  range_id INT,
  number VARCHAR(20),
  status ENUM('available','assigned','blacklisted') DEFAULT 'available',
  CONSTRAINT fk_numbers_range FOREIGN KEY (range_id)
    REFERENCES ranges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  balance DECIMAL(10,2) DEFAULT 0,
  role ENUM('user','admin') DEFAULT 'user',
  numbers_limit INT DEFAULT 10,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payouts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  amount DECIMAL(10,2),
  status ENUM('pending','approved','paid','rejected') DEFAULT 'pending',
  method VARCHAR(50),
  details VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  CONSTRAINT fk_payouts_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  range_id INT,
  sms_count INT DEFAULT 1,
  cost DECIMAL(10,4),
  country VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sms_logs_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sms_logs_range FOREIGN KEY (range_id)
    REFERENCES ranges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;