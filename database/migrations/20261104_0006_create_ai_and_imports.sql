-- F2S Pro — AI usage log, AI credit ledger and form imports (Bridge).

-- One row per AI API call (MANIFEST §5): model, tokens, cost, operation.
CREATE TABLE token_usage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    job_id BIGINT UNSIGNED NULL,
    operation VARCHAR(48) NOT NULL,
    model VARCHAR(80) NOT NULL,
    prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    cost_micro_usd INT UNSIGNED NOT NULL DEFAULT 0,
    success TINYINT(1) NOT NULL DEFAULT 1,
    duration_ms INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_token_usage_org_date (organization_id, created_at),
    CONSTRAINT fk_token_usage_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI credits: negative delta = spent, positive = manual grant. The plan allowance
-- is computed from the plan (ai_credits / ai_credits_per_month), not stored here.
CREATE TABLE credit_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    delta INT NOT NULL,
    reason VARCHAR(48) NOT NULL,
    reference VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_credit_ledger_org_date (organization_id, created_at),
    UNIQUE KEY uq_credit_ledger_reference (organization_id, reason, reference),
    CONSTRAINT fk_credit_ledger_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A form import awaiting confirmation: converted schema + conversion report
-- (kept / transformed / lost / unsupported). The uploaded file itself is not kept.
CREATE TABLE imports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    source_format VARCHAR(32) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    schema_json JSON NULL,
    report_json JSON NOT NULL,
    form_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME NULL,
    UNIQUE KEY uq_imports_public_id (public_id),
    KEY idx_imports_org_project (organization_id, project_id),
    CONSTRAINT fk_imports_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_imports_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_imports_form FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE SET NULL,
    CONSTRAINT chk_imports_status CHECK (status IN ('pending', 'confirmed', 'cancelled', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
