CREATE TABLE IF NOT EXISTS fisitaap_r1_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_r1_role_name (tenant_id,name),
    UNIQUE KEY uq_r1_role_tenant (id,tenant_id),
    CONSTRAINT fk_r1_role_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fisitaap_r1_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    section_key VARCHAR(60) NOT NULL,
    action_key VARCHAR(30) NOT NULL,
    PRIMARY KEY (role_id,section_key,action_key),
    CONSTRAINT fk_r1_permission_role FOREIGN KEY (role_id) REFERENCES fisitaap_r1_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fisitaap_r1_user_roles (
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    previous_role VARCHAR(30) NOT NULL,
    KEY ix_r1_assignment_role (role_id,tenant_id),
    CONSTRAINT fk_r1_assignment_role FOREIGN KEY (role_id,tenant_id) REFERENCES fisitaap_r1_roles(id,tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_r1_assignment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
