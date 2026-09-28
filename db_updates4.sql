USE ict_helpdesk;

-- ─────────────────────────────────────────────────────────────────
--  v5 Upgrades: Email SMTP logging + session activity tracking
-- ─────────────────────────────────────────────────────────────────

-- 1. Add error_message & sent_at columns to email_notifications
--    (safe to run even if table already exists)
ALTER TABLE email_notifications
  ADD COLUMN IF NOT EXISTS error_message TEXT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER error_message;

-- 2. Create email_notifications if it doesn't exist yet
CREATE TABLE IF NOT EXISTS email_notifications (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id        INT NOT NULL,
    recipient_email  VARCHAR(150) NOT NULL,
    subject          VARCHAR(255) NOT NULL,
    status           ENUM('sent','failed') DEFAULT 'failed',
    error_message    TEXT NULL,
    sent_at          DATETIME NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);

SELECT 'v5 DB updated — email_notifications & session support ready!' AS msg;
