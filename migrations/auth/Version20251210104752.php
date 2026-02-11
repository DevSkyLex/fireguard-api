<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Authorization module tables (RBAC).
 */
final class Version20251210104752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates permissions, roles, role_permissions, and role_assignments tables for RBAC';
    }

    public function up(Schema $schema): void
    {
        // Create permissions table
        $this->addSql('CREATE TABLE permissions (
            id VARCHAR(36) NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_permissions_name ON permissions (name)');
        $this->addSql('COMMENT ON COLUMN permissions.created_at IS \'(DC2Type:datetime_immutable)\'');

        // Create roles table
        $this->addSql('CREATE TABLE roles (
            id VARCHAR(36) NOT NULL,
            name VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            is_system BOOLEAN NOT NULL DEFAULT FALSE,
            tenant_id VARCHAR(36) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_roles_name ON roles (name)');
        $this->addSql('CREATE INDEX idx_roles_tenant ON roles (tenant_id)');
        $this->addSql('CREATE INDEX idx_roles_name ON roles (name)');
        $this->addSql('COMMENT ON COLUMN roles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN roles.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Create role_permissions junction table
        $this->addSql('CREATE TABLE role_permissions (
            role_id VARCHAR(36) NOT NULL,
            permission_id VARCHAR(36) NOT NULL,
            PRIMARY KEY(role_id, permission_id)
        )');
        $this->addSql('CREATE INDEX idx_role_permissions_role ON role_permissions (role_id)');
        $this->addSql('CREATE INDEX idx_role_permissions_permission ON role_permissions (permission_id)');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Create role_assignments table
        $this->addSql('CREATE TABLE role_assignments (
            id VARCHAR(36) NOT NULL,
            role_id VARCHAR(36) NOT NULL,
            subject_type VARCHAR(20) NOT NULL,
            subject_id VARCHAR(36) NOT NULL,
            tenant_id VARCHAR(36) DEFAULT NULL,
            organization_id VARCHAR(36) DEFAULT NULL,
            assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_role_assignments_role ON role_assignments (role_id)');
        $this->addSql('CREATE INDEX idx_role_assignments_subject ON role_assignments (subject_type, subject_id)');
        $this->addSql('CREATE INDEX idx_role_assignments_tenant ON role_assignments (tenant_id)');
        $this->addSql('CREATE INDEX idx_role_assignments_organization ON role_assignments (organization_id)');
        $this->addSql('ALTER TABLE role_assignments ADD CONSTRAINT fk_role_assignments_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('COMMENT ON COLUMN role_assignments.assigned_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN role_assignments.expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role_assignments DROP CONSTRAINT IF EXISTS fk_role_assignments_role');
        $this->addSql('ALTER TABLE role_permissions DROP CONSTRAINT IF EXISTS fk_role_permissions_role');
        $this->addSql('ALTER TABLE role_permissions DROP CONSTRAINT IF EXISTS fk_role_permissions_permission');
        $this->addSql('DROP TABLE IF EXISTS role_assignments');
        $this->addSql('DROP TABLE IF EXISTS role_permissions');
        $this->addSql('DROP TABLE IF EXISTS roles');
        $this->addSql('DROP TABLE IF EXISTS permissions');
    }
}

