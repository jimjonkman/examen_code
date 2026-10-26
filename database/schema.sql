-- MariaDB 10.5+ / InnoDB. Selecteer eerst de database; dit bestand bevat bewust geen USE.
-- Gebruik UTC voor databaseverbindingen en datums. Thema's staan uitsluitend in bestanden.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    mail_from_name VARCHAR(160) NOT NULL,
    mail_from_address VARCHAR(254) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    mail_templates LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_slug (slug),
    CONSTRAINT ck_companies_templates CHECK (mail_templates IS NULL OR JSON_VALID(mail_templates)),
    CONSTRAINT ck_companies_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    role ENUM('applicant', 'employee', 'admin') NOT NULL,
    department VARCHAR(64) NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_company_email (company_id, email),
    UNIQUE KEY uq_users_company_id (company_id, id),
    KEY ix_users_company_role (company_id, role, is_active),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT ck_users_active CHECK (is_active IN (0, 1)),
    CONSTRAINT ck_users_department CHECK (department IS NULL OR department IN
        ('assembly-technician', 'bodyshop-coating-technician', 'production-employee-drones', 'open/intern')),
    CONSTRAINT ck_users_change_password CHECK (must_change_password IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    position VARCHAR(64) NOT NULL DEFAULT 'open-application',
    department VARCHAR(64) GENERATED ALWAYS AS
        (CASE WHEN position IN ('mbo-hbo-internship', 'open-application') THEN 'open/intern' ELSE position END) STORED,
    cv_path VARCHAR(255) NOT NULL,
    letter_path VARCHAR(255) NOT NULL,
    cv_name VARCHAR(255) NOT NULL,
    letter_name VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_applications_company_id (company_id, id),
    UNIQUE KEY uq_applications_company_user (company_id, user_id),
    KEY ix_applications_company_status (company_id, status, created_at),
    KEY ix_applications_company_department (company_id, department, status, created_at),
    KEY ix_applications_company_reviewer (company_id, reviewed_by),
    CONSTRAINT ck_applications_position CHECK (position IN
        ('assembly-technician', 'bodyshop-coating-technician', 'production-employee-drones', 'mbo-hbo-internship', 'open-application')),
    CONSTRAINT fk_applications_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_applications_user FOREIGN KEY (company_id, user_id)
        REFERENCES users (company_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_applications_reviewer FOREIGN KEY (company_id, reviewed_by)
        REFERENCES users (company_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    purpose ENUM('set_password', 'verify_email') NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_tokens_hash (token_hash),
    KEY ix_auth_tokens_company_user (company_id, user_id, purpose),
    KEY ix_auth_tokens_expiry (expires_at),
    CONSTRAINT fk_auth_tokens_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_auth_tokens_user FOREIGN KEY (company_id, user_id)
        REFERENCES users (company_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE working_hours (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT 'ISO: maandag=1, zondag=7',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_working_hours_interval (company_id, employee_id, weekday, start_time),
    CONSTRAINT fk_working_hours_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_working_hours_employee FOREIGN KEY (company_id, employee_id)
        REFERENCES users (company_id, id) ON DELETE CASCADE,
    CONSTRAINT ck_working_hours_weekday CHECK (weekday BETWEEN 1 AND 7),
    CONSTRAINT ck_working_hours_interval CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('scheduled', 'cancelled') NOT NULL DEFAULT 'scheduled',
    outlook_event_id VARCHAR(512) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- NULL neemt niet deel aan de unieke index: annuleren maakt opnieuw boeken mogelijk.
    active_application_id BIGINT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN status = 'scheduled' THEN application_id ELSE NULL END) STORED,
    active_starts_at DATETIME GENERATED ALWAYS AS
        (CASE WHEN status = 'scheduled' THEN starts_at ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_appointments_active_application (company_id, active_application_id),
    UNIQUE KEY uq_appointments_active_employee_slot (company_id, employee_id, active_starts_at),
    KEY ix_appointments_application (company_id, application_id),
    KEY ix_appointments_employee_period (company_id, employee_id, starts_at, ends_at),
    CONSTRAINT fk_appointments_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_appointments_application FOREIGN KEY (company_id, application_id)
        REFERENCES applications (company_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_employee FOREIGN KEY (company_id, employee_id)
        REFERENCES users (company_id, id),
    CONSTRAINT ck_appointments_interval CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_outlook_settings (
    company_id BIGINT UNSIGNED NOT NULL,
    tenant_id VARCHAR(120) NULL,
    client_id VARCHAR(120) NULL,
    secret_ref VARCHAR(120) NULL COMMENT 'Alleen naam van omgevingsvariabele, nooit een secret',
    PRIMARY KEY (company_id),
    CONSTRAINT fk_outlook_company FOREIGN KEY (company_id) REFERENCES companies (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    rate_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_start DATETIME NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (rate_key),
    KEY ix_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    recipient VARCHAR(254) NOT NULL,
    template VARCHAR(40) NOT NULL,
    payload LONGTEXT NOT NULL COMMENT 'Versleuteld bericht; tokens mogen niet leesbaar opgeslagen worden',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(120) NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY ix_mail_queue_company (company_id),
    KEY ix_mail_queue_ready (sent_at, available_at, attempts),
    CONSTRAINT fk_mail_queue_company FOREIGN KEY (company_id) REFERENCES companies (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
