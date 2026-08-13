SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10),
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipalities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    code VARCHAR(10),
    name VARCHAR(120) NOT NULL,
    is_enabled_for_reports TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_municipalities_department_enabled (department_id,is_enabled_for_reports),
    CONSTRAINT fk_municipalities_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(40),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('reviewer','admin') NOT NULL DEFAULT 'reviewer',
    status ENUM('active','suspended','revoked') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviewer_profiles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE,
    profession VARCHAR(150),
    license_number VARCHAR(100),
    organization_name VARCHAR(190),
    verification_status ENUM('pending','verified','suspended','revoked') NOT NULL DEFAULT 'pending',
    bio TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_reviewer_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS properties (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL UNIQUE,
    department_id INT NOT NULL,
    municipality_id INT NOT NULL,
    neighborhood VARCHAR(160) NOT NULL,
    sector VARCHAR(160),
    address_private VARCHAR(255) NOT NULL,
    latitude_private DECIMAL(10,7) NULL,
    longitude_private DECIMAL(10,7) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_properties_location (municipality_id,neighborhood),
    CONSTRAINT fk_properties_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_properties_municipality FOREIGN KEY (municipality_id) REFERENCES municipalities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS damage_reports (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    public_code VARCHAR(40) NOT NULL UNIQUE,
    property_id BIGINT NOT NULL,
    reporter_name_private VARCHAR(160) NOT NULL,
    reporter_phone_private VARCHAR(50) NOT NULL,
    reporter_email_private VARCHAR(190),
    description_public TEXT NOT NULL,
    perceived_priority ENUM('urgent','high','medium','low') NOT NULL,
    system_priority ENUM('urgent','high','medium','low') NOT NULL,
    status ENUM('pending','assigned','contacted','scheduled','reviewing','reviewed','second_opinion','referred','closed') NOT NULL DEFAULT 'pending',
    moderation_status ENUM('published','hidden','rejected') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_damage_reports_status_priority (status,system_priority,created_at),
    CONSTRAINT fk_damage_reports_property FOREIGN KEY (property_id) REFERENCES properties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS damage_answers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT NOT NULL,
    question_key VARCHAR(80) NOT NULL,
    answer_value VARCHAR(50),
    CONSTRAINT fk_damage_answers_report FOREIGN KEY (report_id) REFERENCES damage_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS damage_photos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT NOT NULL,
    storage_disk VARCHAR(30) NOT NULL DEFAULT 'local',
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(80),
    file_size INT,
    width INT NULL,
    height INT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    moderation_status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'approved',
    created_at DATETIME NOT NULL,
    INDEX idx_damage_photos_report (report_id),
    CONSTRAINT fk_damage_photos_report FOREIGN KEY (report_id) REFERENCES damage_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_assignments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT NOT NULL,
    reviewer_id BIGINT NOT NULL,
    assigned_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    released_at DATETIME NULL,
    status ENUM('active','completed','released','expired') NOT NULL DEFAULT 'active',
    INDEX idx_assignments_report_status (report_id,status),
    INDEX idx_assignments_reviewer_status (reviewer_id,status),
    CONSTRAINT fk_assignments_report FOREIGN KEY (report_id) REFERENCES damage_reports(id),
    CONSTRAINT fk_assignments_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inspections (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT NOT NULL,
    reviewer_id BIGINT NOT NULL,
    inspection_type VARCHAR(80),
    inspection_date DATE,
    findings_public TEXT,
    recommendation VARCHAR(255),
    public_diagnosis TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_inspections_report (report_id),
    CONSTRAINT fk_inspections_report FOREIGN KEY (report_id) REFERENCES damage_reports(id),
    CONSTRAINT fk_inspections_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT NOT NULL,
    actor_user_id BIGINT NULL,
    event_type VARCHAR(80) NOT NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_report_events_report_date (report_id,created_at),
    CONSTRAINT fk_report_events_report FOREIGN KEY (report_id) REFERENCES damage_reports(id),
    CONSTRAINT fk_report_events_user FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_flags (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT NOT NULL,
    reporter_user_id BIGINT NULL,
    reason VARCHAR(255),
    status ENUM('open','resolved','dismissed') DEFAULT 'open',
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_report_flags_report FOREIGN KEY (report_id) REFERENCES damage_reports(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
