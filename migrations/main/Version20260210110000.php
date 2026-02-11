<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Organization core tables in main database';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organizations (id VARCHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, created_by_user_id VARCHAR(36) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_organization_created_by_user ON organizations (created_by_user_id)');
        $this->addSql("COMMENT ON COLUMN organizations.created_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE organization_roles (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, name VARCHAR(50) NOT NULL, permissions JSON NOT NULL, is_system BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_organization_role_organization ON organization_roles (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_role_name ON organization_roles (organization_id, name)');
        $this->addSql("COMMENT ON COLUMN organization_roles.created_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE organization_members (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, is_active BOOLEAN NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_organization_member_organization ON organization_members (organization_id)');
        $this->addSql('CREATE INDEX idx_organization_member_user ON organization_members (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_user_member ON organization_members (organization_id, user_id)');
        $this->addSql("COMMENT ON COLUMN organization_members.joined_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE organization_member_roles (member_id VARCHAR(36) NOT NULL, role_id VARCHAR(36) NOT NULL, assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(member_id, role_id))');
        $this->addSql('CREATE INDEX idx_organization_member_roles_role ON organization_member_roles (role_id)');
        $this->addSql("COMMENT ON COLUMN organization_member_roles.assigned_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE organization_roles ADD CONSTRAINT fk_organization_role_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_members ADD CONSTRAINT fk_organization_member_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_member_roles ADD CONSTRAINT fk_organization_member_role_member FOREIGN KEY (member_id) REFERENCES organization_members (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_member_roles ADD CONSTRAINT fk_organization_member_role_role FOREIGN KEY (role_id) REFERENCES organization_roles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_member_roles DROP CONSTRAINT fk_organization_member_role_member');
        $this->addSql('ALTER TABLE organization_member_roles DROP CONSTRAINT fk_organization_member_role_role');
        $this->addSql('ALTER TABLE organization_members DROP CONSTRAINT fk_organization_member_organization');
        $this->addSql('ALTER TABLE organization_roles DROP CONSTRAINT fk_organization_role_organization');

        $this->addSql('DROP TABLE organization_member_roles');
        $this->addSql('DROP TABLE organization_members');
        $this->addSql('DROP TABLE organization_roles');
        $this->addSql('DROP TABLE organizations');
    }
}
