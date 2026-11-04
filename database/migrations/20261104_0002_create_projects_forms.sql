-- F2S Pro — Studio: projects, forms, immutable form versions, question projection.

CREATE TABLE projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    archived_at DATETIME NULL,
    UNIQUE KEY uq_projects_public_id (public_id),
    KEY idx_projects_org_ws (organization_id, workspace_id),
    CONSTRAINT fk_projects_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_projects_ws FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE,
    CONSTRAINT fk_projects_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    form_key VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    current_version_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    archived_at DATETIME NULL,
    UNIQUE KEY uq_forms_public_id (public_id),
    KEY idx_forms_org_project (organization_id, project_id),
    CONSTRAINT fk_forms_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_forms_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_forms_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A published version is never modified (MANIFEST rule 3). Enforced in
-- FormRepository (status = 'draft' in every UPDATE's WHERE clause) and by tests.
CREATE TABLE form_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    form_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    schema_version VARCHAR(8) NOT NULL DEFAULT '1.0',
    schema_json JSON NOT NULL,
    schema_hash CHAR(64) NOT NULL,
    question_count INT UNSIGNED NOT NULL DEFAULT 0,
    source VARCHAR(32) NOT NULL DEFAULT 'json',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    UNIQUE KEY uq_form_versions_public_id (public_id),
    UNIQUE KEY uq_form_versions_number (form_id, version_number),
    KEY idx_form_versions_org (organization_id, form_id),
    CONSTRAINT fk_form_versions_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_form_versions_form FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE,
    CONSTRAINT fk_form_versions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_form_versions_status CHECK (status IN ('draft', 'published'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE forms
    ADD CONSTRAINT fk_forms_current_version FOREIGN KEY (current_version_id) REFERENCES form_versions (id) ON DELETE SET NULL;

-- Indexed projection of a version's schema (rebuildable from schema_json).
CREATE TABLE questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    form_version_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(64) NOT NULL,
    type VARCHAR(32) NOT NULL,
    path VARCHAR(512) NOT NULL,
    label TEXT NOT NULL,
    choice_list VARCHAR(64) NULL,
    analysis_role VARCHAR(32) NULL,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    is_personal TINYINT(1) NOT NULL DEFAULT 0,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_questions_version_name (form_version_id, name),
    KEY idx_questions_org (organization_id),
    CONSTRAINT fk_questions_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_questions_version FOREIGN KEY (form_version_id) REFERENCES form_versions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
