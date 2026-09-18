<?php

declare(strict_types=1);

/**
 * CLI: create the full MySQL schema (raw DDL).
 *
 * Port of the Features/*.sql.ts create() methods and backend/cli/create.ts.
 *
 * Usage (from repo root):  php backend/cli/create.php
 *
 * Notes on the port:
 *  - SERIAL             -> INT AUTO_INCREMENT PRIMARY KEY
 *  - JSONB              -> JSON
 *  - BOOLEAN            -> TINYINT(1)
 *  - NUMERIC(p,s)       -> DECIMAL(p,s)
 *  - pg_trgm / GIST indexes are dropped (no trigram in MySQL; autocomplete uses LIKE later).
 *  - MySQL DDL auto-commits, so statements run sequentially (no transaction needed).
 *  - Tables are created in FK dependency order.
 */

use Cenusis\Db\Db;

$backendDir = dirname(__DIR__);

if (file_exists($backendDir . '/vendor/autoload.php')) {
    require $backendDir . '/vendor/autoload.php';
} else {
    require_once $backendDir . '/src/Db/Db.php';
    require_once $backendDir . '/src/helpers/helpers.php';
}

/**
 * CREATE INDEX has no IF NOT EXISTS on MySQL 8, so check information_schema first.
 */
function index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

function run_ddl(PDO $pdo, string $label, string $sql): void
{
    $pdo->exec($sql);
    echo "  [OK]    {$label}\n";
}

$pdo = Db::pdo();
$pdo->exec("SET NAMES utf8mb4");

$tables = [
    // 1. loggedin_users (PG_AuthTable: password_hash added automatically)
    'loggedin_users' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS loggedin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            normalized_username VARCHAR(150) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 2. teaching_staff (needs loggedin_users)
    'teaching_staff' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS teaching_staff (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_name VARCHAR(150) NOT NULL,
            teacher_normalized_name VARCHAR(150) UNIQUE NOT NULL,
            login_credentials INT NOT NULL,
            availability_bitmap BIGINT DEFAULT 0,
            hours_available INT DEFAULT 0,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT chk_teaching_staff_hours CHECK (hours_available >= 0),
            CONSTRAINT fk_teaching_staff_login
                FOREIGN KEY (login_credentials) REFERENCES loggedin_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 3. grading_systems (referenced by subjects + absence_alert_thresholds)
    'grading_systems' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS grading_systems (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            normalized_name VARCHAR(150) UNIQUE NOT NULL,
            fields JSON NOT NULL DEFAULT ('[]'),
            deleted_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 4. students (importData also uses sex / years_retaken / years_failed)
    'students' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_name VARCHAR(150) NOT NULL,
            student_normalized_name VARCHAR(150) UNIQUE NOT NULL,
            degree VARCHAR(20) NOT NULL,
            class INT NOT NULL,
            sex VARCHAR(20) NOT NULL DEFAULT '',
            years_retaken INT NOT NULL DEFAULT 0,
            years_failed INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 5. subjects (needs teaching_staff + grading_systems)
    'subjects' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_name VARCHAR(150) NOT NULL,
            subject_normalized_name VARCHAR(150) UNIQUE NOT NULL,
            teacher INT NOT NULL,
            degree VARCHAR(150) NOT NULL,
            class INT NOT NULL,
            total_hours INT NOT NULL,
            hours_weekly INT NOT NULL,
            is_attending_required TINYINT(1) DEFAULT 0,
            semester INT NOT NULL,
            grading_system_id INT NULL,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            lab_teacher INT DEFAULT NULL,
            max_lab_grade INT NULL,
            lab_grade_field TEXT NULL,
            lab_weekly_hours INT DEFAULT 0,
            CONSTRAINT chk_subjects_semester CHECK (semester BETWEEN 1 AND 2),
            CONSTRAINT chk_subjects_class CHECK (class BETWEEN 1 AND 4),
            CONSTRAINT chk_subjects_total_hours CHECK (total_hours > 0),
            CONSTRAINT chk_subjects_hours_weekly CHECK (hours_weekly > 0 AND hours_weekly <= total_hours),
            CONSTRAINT chk_subjects_lab_hours CHECK (lab_weekly_hours >= 0),
            CONSTRAINT fk_subjects_lab_teacher
                FOREIGN KEY (lab_teacher) REFERENCES teaching_staff(id),
            CONSTRAINT fk_subjects_teacher
                FOREIGN KEY (teacher) REFERENCES teaching_staff(id) ON DELETE CASCADE,
            CONSTRAINT fk_subjects_grading_system
                FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 6. studying (needs students, subjects, teaching_staff)
    'studying' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS studying (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher INT NOT NULL,
            student INT NOT NULL,
            subject INT NOT NULL,
            studying_year INT NOT NULL,
            hours_missed INT DEFAULT 0,
            grade_fields JSON NOT NULL DEFAULT ('[]'),
            lab_grade INT DEFAULT 0,
            is_submitted TINYINT(1) DEFAULT 0,
            exam_retakes INT DEFAULT 0,
            semester_retakes INT DEFAULT 0,
            is_attending_required TINYINT(1) DEFAULT 1,
            alert_level VARCHAR(150) NULL DEFAULT NULL,
            CONSTRAINT chk_studying_hours_missed CHECK (hours_missed >= 0),
            CONSTRAINT uq_studying_student_subject UNIQUE (student, subject),
            CONSTRAINT fk_studying_student
                FOREIGN KEY (student) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_studying_subject
                FOREIGN KEY (subject) REFERENCES subjects(id) ON DELETE CASCADE,
            CONSTRAINT fk_studying_teacher
                FOREIGN KEY (teacher) REFERENCES teaching_staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 7. attendance_record (needs subjects)
    'attendance_record' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS attendance_record (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject INT NOT NULL,
            `date` VARCHAR(10) NOT NULL,
            lab_attendance TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_attendance_record_subject
                FOREIGN KEY (subject) REFERENCES subjects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 8. absented (needs attendance_record + students)
    'absented' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS absented (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attendance_record INT NOT NULL,
            student INT NOT NULL,
            hours_absent INT NOT NULL,
            CONSTRAINT fk_absented_record
                FOREIGN KEY (attendance_record) REFERENCES attendance_record(id) ON DELETE CASCADE,
            CONSTRAINT fk_absented_student
                FOREIGN KEY (student) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 9. absence_alert_thresholds (needs grading_systems)
    'absence_alert_thresholds' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS absence_alert_thresholds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grading_system_id INT NOT NULL,
            alert_name VARCHAR(150) NOT NULL,
            threshold_percent DECIMAL(5,2) NOT NULL,
            CONSTRAINT absence_alert_thresholds_percent_check
                CHECK (threshold_percent >= 0 AND threshold_percent <= 100),
            CONSTRAINT absence_alert_thresholds_unique_name UNIQUE (grading_system_id, alert_name),
            CONSTRAINT fk_absence_alert_thresholds_gs
                FOREIGN KEY (grading_system_id) REFERENCES grading_systems(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 10. miniapps (PG_AuthTable with identify field miniapp_code, so it also gets
    //      a password_hash column, mirroring loggedin_users)
    'miniapps' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS miniapps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            miniapp_code VARCHAR(100) UNIQUE NOT NULL,
            miniapp_name VARCHAR(200) NOT NULL,
            miniapp_author VARCHAR(200) NOT NULL,
            miniapp_homepage VARCHAR(1024) NOT NULL,
            miniapp_icons TEXT NULL,
            password_hash VARCHAR(255) NOT NULL,
            UNIQUE KEY uq_miniapps_icons (miniapp_icons(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 11. miniapp_permissions (needs miniapps)
    'miniapp_permissions' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS miniapp_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            granted_minimapp INT NOT NULL,
            granted_action VARCHAR(100) NOT NULL,
            CONSTRAINT fk_miniapp_permissions_app
                FOREIGN KEY (granted_minimapp) REFERENCES miniapps(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    // 12. cenusis_settings (app_settings.sql.ts)
    //     NOTE: "option" is a reserved word in MySQL -> backtick quoted.
    'cenusis_settings' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS cenusis_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `option` VARCHAR(100) UNIQUE NOT NULL,
            value JSON NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
];

// Plain BTREE indexes ported from the .sql.ts files (GIST/pg_trgm ones are dropped).
// MySQL requires unique index names per table; the TS files had two duplicates
// (idx_teaching_staff_normalized_name, idx_subjects_teacher) that were renamed here.
$indexes = [
    ['idx_teaching_staff_login_credentials', 'teaching_staff', 'login_credentials'],
    ['idx_subjects_teacher', 'subjects', 'teacher'],
    ['idx_subjects_lab_teacher', 'subjects', 'lab_teacher'],
    ['idx_subjects_grading_system_id', 'subjects', 'grading_system_id'],
    ['idx_studying_teacher', 'studying', 'teacher'],
    ['idx_studying_student_subject', 'studying', 'student, subject'],
    ['idx_absence_alert_thresholds_grading_system_id', 'absence_alert_thresholds', 'grading_system_id'],
    ['idx_cenusis_settings_option', 'cenusis_settings', '`option`'],
];

echo "Creating tables...\n";

foreach ($tables as $table => $ddl) {
    run_ddl($pdo, "table {$table}", $ddl);
}

foreach ($indexes as [$indexName, $table, $columns]) {
    if (index_exists($pdo, $table, $indexName)) {
        echo "  [SKIP]  index {$indexName} already exists\n";
        continue;
    }
    run_ddl($pdo, "index {$indexName}", "CREATE INDEX `{$indexName}` ON `{$table}` ({$columns})");
}

echo "Tables created successfully\n";
