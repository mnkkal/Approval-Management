-- ==============================================================================
-- Schema Migration 002: Projects, Deliverables & Version Iterations
-- ==============================================================================

-- 1. Projects
CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    client_organization_id UUID REFERENCES client_organizations(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(32) NOT NULL DEFAULT 'active', -- active, on_hold, completed, archived
    due_date DATE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE
);

CREATE INDEX idx_projects_workspace_status ON projects(workspace_id, status);

-- 2. Deliverables (Container for assets across versions)
CREATE TABLE deliverables (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(32) NOT NULL, -- image, pdf, video, staging_url
    status VARCHAR(32) NOT NULL DEFAULT 'draft', -- draft, internal_review, client_review, changes_requested, approved, archived
    current_version_number INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_deliverables_project ON deliverables(project_id);
CREATE INDEX idx_deliverables_status ON deliverables(status);

-- 3. Deliverable Versions (Immutable asset records bound by SHA-256)
CREATE TABLE deliverable_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    deliverable_id UUID NOT NULL REFERENCES deliverables(id) ON DELETE CASCADE,
    version_number INT NOT NULL,
    storage_path VARCHAR(1024) NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    preview_status VARCHAR(32) NOT NULL DEFAULT 'ready', -- ready, processing, preview_unavailable
    preview_url VARCHAR(1024),
    mime_type VARCHAR(128) NOT NULL,
    file_size_bytes BIGINT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}', -- dimensions, duration_seconds, page_count, codec
    changelog TEXT,
    created_by_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_deliverable_version_num UNIQUE (deliverable_id, version_number)
);

CREATE INDEX idx_versions_deliverable ON deliverable_versions(deliverable_id, version_number);
CREATE INDEX idx_versions_sha256 ON deliverable_versions(content_sha256);
