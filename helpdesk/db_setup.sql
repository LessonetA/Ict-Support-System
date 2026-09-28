-- ============================================
-- ICT HELPDESK SYSTEM v4 — Full Database Setup
-- Run this ONCE in phpMyAdmin to set up everything
-- ============================================
CREATE DATABASE IF NOT EXISTS ict_helpdesk;
USE ict_helpdesk;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','staff','admin') DEFAULT 'user',
    status ENUM('pending','active','suspended') DEFAULT 'active',
    department VARCHAR(100),
    phone VARCHAR(20),
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low','medium','high','critical') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    staff_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ticket_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rating (ticket_id, user_id)
);

CREATE TABLE IF NOT EXISTS sla_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    priority VARCHAR(20) NOT NULL UNIQUE,
    hours_to_resolve INT NOT NULL,
    hours_to_respond INT NOT NULL
);

CREATE TABLE IF NOT EXISTS knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    views INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','warning','success','danger') DEFAULT 'info',
    admin_id INT,
    expires_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT,
    recipient_email VARCHAR(100),
    subject VARCHAR(200),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent','failed') DEFAULT 'sent',
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
);

-- SEED DATA
INSERT IGNORE INTO categories (name, description) VALUES
('Hardware','Printers, monitors, keyboards, mice, computers'),
('Software','Installations, errors, licensing, crashes'),
('Network','Internet, WiFi, LAN, connectivity issues'),
('Email','Email setup, access, and configuration'),
('Account Access','Password resets, lockouts, permissions'),
('Other','Any other ICT related issue');

INSERT IGNORE INTO sla_settings (priority, hours_to_resolve, hours_to_respond) VALUES
('low',72,24),('medium',48,8),('high',24,4),('critical',8,1);

-- Default accounts (password: password)
INSERT IGNORE INTO users (full_name,email,password,role,department,status) VALUES
('System Admin','admin@helpdesk.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','ICT Department','active'),
('IT Support Staff','staff@helpdesk.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','staff','ICT Department','active'),
('John Doe','user@helpdesk.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','user','Finance','active');

INSERT IGNORE INTO knowledge_base (title,content,category,created_by) VALUES
('How to reset your password','1. Click "Forgot Password" on the login page\n2. Enter your registered email\n3. Check your email for a reset link\n4. Click the link and set a new password\n\nIf still locked out, raise a ticket under "Account Access".','Account Access',1),
('Connecting to Office WiFi','1. Click the WiFi icon in the system tray\n2. Select "Office-WiFi"\n3. Enter the password (contact ICT for current password)\n4. Click Connect\n\nIf network is not visible, ensure WiFi adapter is enabled.','Network',1),
('Printer not printing','1. Check printer is powered on and has paper\n2. Check USB or network cable\n3. Open Control Panel > Devices and Printers\n4. Right-click printer > See what\'s printing\n5. Cancel all jobs and retry\n6. Restart printer and computer\n\nIf issue persists, raise a Hardware ticket.','Hardware',1),
('Installing Microsoft Office','1. Go to https://portal.office.com\n2. Sign in with your company email\n3. Click "Install Office"\n4. Run the installer\n5. Follow on-screen instructions\n\nNote: Must be connected to internet. Contact ICT if you need a license.','Software',1);

INSERT IGNORE INTO announcements (title,message,type,admin_id,expires_at) VALUES
('Welcome to ICT Helpdesk v4','Our new helpdesk system is now live with dark mode, live analytics, SLA timers, and a satisfaction rating system. Raise your first ticket to get started!','success',1,DATE_ADD(CURDATE(),INTERVAL 30 DAY));

SELECT 'ICT Helpdesk v4 database setup complete!' as message;
