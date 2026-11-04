-- F2S Pro — SQL job queue processed by CRON (ADR 0002). Replaces the
-- ai_jobs / analysis_jobs tables of the original manifest: one table, `type`
-- tells the job apart, `runner` selects PHP (shared hosting) or a future Python worker.

CREATE TABLE jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    type VARCHAR(64) NOT NULL,
    runner VARCHAR(16) NOT NULL DEFAULT 'php',
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    priority TINYINT NOT NULL DEFAULT 0,
    payload JSON NOT NULL,
    progress JSON NULL,
    result JSON NULL,
    error TEXT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at DATETIME NULL,
    reserved_by VARCHAR(64) NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_jobs_queue (status, runner, available_at, priority),
    KEY idx_jobs_org (organization_id, type),
    CONSTRAINT fk_jobs_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT chk_jobs_status CHECK (status IN ('pending', 'running', 'done', 'failed')),
    CONSTRAINT chk_jobs_runner CHECK (runner IN ('php', 'python'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
