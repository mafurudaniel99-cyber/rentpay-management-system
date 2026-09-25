-- ============================================================
-- RentPay :: Rent Management System
-- Full MySQL Database Schema (PDO / MySQL 8+)
-- Extends the base README schema with Applications, Escrow,
-- Disputes, and Verification Documents to support the full
-- landlord verification + escrow payment workflow.
-- ============================================================

CREATE DATABASE IF NOT EXISTS rentpay_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rentpay_db;

-- ------------------------------------------------------------
-- USERS  (base account for Admin, Landlord, Tenant)
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    phone       VARCHAR(20)  NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('ADMIN','LANDLORD','TENANT') NOT NULL,
    status      ENUM('PENDING_REVIEW','APPROVED','ACTIVE','REJECTED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    rejection_reason  VARCHAR(255) NULL,
    suspension_reason VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- LANDLORDS  (extends users)
-- ------------------------------------------------------------
CREATE TABLE landlords (
    landlord_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    national_id  VARCHAR(50) NULL,
    address      VARCHAR(255) NULL,
    photo        VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_landlord_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VERIFICATION DOCUMENTS  (BRELA / PDPC uploads for landlords)
-- ------------------------------------------------------------
CREATE TABLE verification_documents (
    doc_id       INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id  INT NOT NULL,
    doc_type     ENUM('BRELA','PDPC','OTHER') NOT NULL,
    file_path    VARCHAR(255) NOT NULL,
    uploaded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_landlord FOREIGN KEY (landlord_id) REFERENCES landlords(landlord_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TENANTS  (extends users)
-- ------------------------------------------------------------
CREATE TABLE tenants (
    tenant_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT NOT NULL,
    landlord_id        INT NULL,
    national_id        VARCHAR(50) NULL,
    occupation         VARCHAR(100) NULL,
    emergency_contact  VARCHAR(100) NULL,
    move_in_date       DATE NULL,
    status             ENUM('ACTIVE','PENDING','MOVED_OUT') NOT NULL DEFAULT 'ACTIVE',
    CONSTRAINT fk_tenant_user     FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_landlord FOREIGN KEY (landlord_id) REFERENCES landlords(landlord_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PROPERTIES
-- ------------------------------------------------------------
CREATE TABLE properties (
    property_id    INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id    INT NOT NULL,
    property_name  VARCHAR(150) NOT NULL,
    location       VARCHAR(255) NOT NULL,
    total_rooms    INT NOT NULL DEFAULT 0,
    description    TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_property_landlord FOREIGN KEY (landlord_id) REFERENCES landlords(landlord_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ROOMS
-- ------------------------------------------------------------
CREATE TABLE rooms (
    room_id      INT AUTO_INCREMENT PRIMARY KEY,
    property_id  INT NOT NULL,
    room_number  VARCHAR(30) NOT NULL,
    rent_amount  DECIMAL(12,2) NOT NULL,
    room_size    VARCHAR(50) NULL,
    status       ENUM('VACANT','RESERVED','OCCUPIED') NOT NULL DEFAULT 'VACANT',
    description  TEXT NULL,
    CONSTRAINT fk_room_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- APPLICATIONS  (tenancy applications, Tenant -> Room)
-- ------------------------------------------------------------
CREATE TABLE applications (
    application_id  INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT NOT NULL,
    room_id         INT NOT NULL,
    message         TEXT NULL,
    status          ENUM('PENDING','APPROVED','REJECTED','WITHDRAWN') NOT NULL DEFAULT 'PENDING',
    submitted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_app_room   FOREIGN KEY (room_id)   REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- INVOICES
-- ------------------------------------------------------------
CREATE TABLE invoices (
    invoice_id   INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT NOT NULL,
    room_id      INT NOT NULL,
    amount_due   DECIMAL(12,2) NOT NULL,
    due_date     DATE NOT NULL,
    issue_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status       ENUM('UNPAID','PAID','OVERDUE') NOT NULL DEFAULT 'UNPAID',
    CONSTRAINT fk_invoice_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_room   FOREIGN KEY (room_id)   REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PAYMENTS  (M-Pesa / manual payments against an invoice)
-- ------------------------------------------------------------
CREATE TABLE payments (
    payment_id        INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id         INT NOT NULL,
    tenant_id          INT NOT NULL,
    room_id            INT NOT NULL,
    amount_paid        DECIMAL(12,2) NOT NULL,
    payment_date       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payment_method     ENUM('MPESA','CASH','BANK_TRANSFER') NOT NULL DEFAULT 'MPESA',
    transaction_code   VARCHAR(100) NULL,
    balance            DECIMAL(12,2) NOT NULL DEFAULT 0,
    status             ENUM('PENDING','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_tenant  FOREIGN KEY (tenant_id)  REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_room    FOREIGN KEY (room_id)    REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ESCROW  (funds held until move-in confirmation / dispute verdict)
-- ------------------------------------------------------------
CREATE TABLE escrow (
    escrow_id    INT AUTO_INCREMENT PRIMARY KEY,
    payment_id   INT NOT NULL,
    landlord_id  INT NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    status       ENUM('HELD','RELEASED','REFUNDED','DISPUTED') NOT NULL DEFAULT 'HELD',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at  DATETIME NULL,
    CONSTRAINT fk_escrow_payment  FOREIGN KEY (payment_id)  REFERENCES payments(payment_id) ON DELETE CASCADE,
    CONSTRAINT fk_escrow_landlord FOREIGN KEY (landlord_id) REFERENCES landlords(landlord_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- WALLETS  (landlord / tenant internal balance)
-- ------------------------------------------------------------
CREATE TABLE wallets (
    wallet_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL UNIQUE,
    balance    DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- EXPENSES
-- ------------------------------------------------------------
CREATE TABLE expenses (
    expense_id    INT AUTO_INCREMENT PRIMARY KEY,
    property_id   INT NOT NULL,
    expense_type  VARCHAR(100) NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    expense_date  DATE NOT NULL,
    description   TEXT NULL,
    CONSTRAINT fk_expense_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MAINTENANCE REQUESTS
-- ------------------------------------------------------------
CREATE TABLE maintenance_requests (
    request_id    INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    room_id       INT NOT NULL,
    title         VARCHAR(150) NOT NULL,
    description   TEXT NULL,
    photo         VARCHAR(255) NULL,
    request_date  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status        ENUM('SUBMITTED','IN_PROGRESS','RESOLVED') NOT NULL DEFAULT 'SUBMITTED',
    CONSTRAINT fk_maint_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_maint_room   FOREIGN KEY (room_id)   REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- RENTAL AGREEMENTS
-- ------------------------------------------------------------
CREATE TABLE rental_agreements (
    agreement_id    INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT NOT NULL,
    room_id         INT NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    deposit_amount  DECIMAL(12,2) NULL,
    start_date      DATE NOT NULL,
    expiry_date     DATE NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agreement_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_agreement_room   FOREIGN KEY (room_id)   REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- DISPUTES
-- ------------------------------------------------------------
CREATE TABLE disputes (
    dispute_id    INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT NOT NULL,
    room_id       INT NOT NULL,
    escrow_id     INT NULL,
    reason        VARCHAR(150) NOT NULL,
    details       TEXT NULL,
    evidence_path VARCHAR(255) NULL,
    status        ENUM('OPEN','RESOLVED') NOT NULL DEFAULT 'OPEN',
    verdict       ENUM('REFUND_TENANT','RELEASE_LANDLORD') NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at   DATETIME NULL,
    CONSTRAINT fk_dispute_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_dispute_room   FOREIGN KEY (room_id)   REFERENCES rooms(room_id) ON DELETE CASCADE,
    CONSTRAINT fk_dispute_escrow FOREIGN KEY (escrow_id) REFERENCES escrow(escrow_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
    notification_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    title            VARCHAR(150) NOT NULL,
    message          TEXT NOT NULL,
    type             ENUM('SYSTEM','PAYMENT','MAINTENANCE','APPLICATION','DISPUTE') NOT NULL DEFAULT 'SYSTEM',
    status           ENUM('UNREAD','READ') NOT NULL DEFAULT 'UNREAD',
    sent_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- AUDIT LOG  (admin actions: approvals, suspensions, verdicts)
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT NOT NULL,
    target_user_id INT NULL,
    action      VARCHAR(100) NOT NULL,
    details     VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Helpful indexes
-- ------------------------------------------------------------
CREATE INDEX idx_rooms_property     ON rooms(property_id);
CREATE INDEX idx_applications_room  ON applications(room_id);
CREATE INDEX idx_payments_tenant    ON payments(tenant_id);
CREATE INDEX idx_escrow_status      ON escrow(status);
CREATE INDEX idx_disputes_status    ON disputes(status);
CREATE INDEX idx_notifications_user ON notifications(user_id, status);

-- ------------------------------------------------------------
-- CONTACT MESSAGES  (public Contact Us form submissions)
-- ------------------------------------------------------------
CREATE TABLE contact_messages (
    message_id   INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(150) NOT NULL,
    email        VARCHAR(150) NOT NULL,
    phone        VARCHAR(20)  NULL,
    inquirer_type ENUM('TENANT','LANDLORD','PARTNER','OTHER') NOT NULL DEFAULT 'OTHER',
    subject      VARCHAR(150) NOT NULL,
    message      TEXT NOT NULL,
    status       ENUM('NEW','READ','RESOLVED') NOT NULL DEFAULT 'NEW',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
