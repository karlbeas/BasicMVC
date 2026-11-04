-- F2S Pro — Collect: campaigns (a published form version opened for responses),
-- submissions (raw input kept as received) and answers (rebuildable projection).

CREATE TABLE campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    form_id BIGINT UNSIGNED NOT NULL,
    form_version_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(16) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'open',
    opens_at DATETIME NULL,
    closes_at DATETIME NULL,
    response_limit INT UNSIGNED NULL,
    access_code_hash VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    closed_at DATETIME NULL,
    UNIQUE KEY uq_campaigns_public_id (public_id),
    UNIQUE KEY uq_campaigns_token (token),
    KEY idx_campaigns_org_status (organization_id, status),
    KEY idx_campaigns_org_created (organization_id, created_at),
    KEY idx_campaigns_form (form_id),
    CONSTRAINT fk_campaigns_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_campaigns_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_campaigns_form FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE,
    CONSTRAINT fk_campaigns_version FOREIGN KEY (form_version_id) REFERENCES form_versions (id),
    CONSTRAINT fk_campaigns_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_campaigns_status CHECK (status IN ('open', 'closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No IP address is stored (privacy by design); duplicates are blocked by submission_uuid.
CREATE TABLE submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    form_version_id BIGINT UNSIGNED NOT NULL,
    submission_uuid CHAR(36) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'complete',
    language VARCHAR(8) NULL,
    raw_json JSON NOT NULL,
    data_json JSON NOT NULL,
    started_at DATETIME NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    duration_seconds INT UNSIGNED NULL,
    UNIQUE KEY uq_submissions_uuid (submission_uuid),
    KEY idx_submissions_campaign (campaign_id, submitted_at),
    KEY idx_submissions_org_date (organization_id, submitted_at),
    CONSTRAINT fk_submissions_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_version FOREIGN KEY (form_version_id) REFERENCES form_versions (id),
    CONSTRAINT chk_submissions_status CHECK (status IN ('complete', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per answered question (one row per selected option for select_multiple).
CREATE TABLE answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    question_name VARCHAR(64) NOT NULL,
    value_text TEXT NULL,
    value_number DECIMAL(20,6) NULL,
    KEY idx_answers_campaign_question (campaign_id, question_name),
    KEY idx_answers_submission (submission_id),
    KEY idx_answers_org (organization_id),
    CONSTRAINT fk_answers_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
