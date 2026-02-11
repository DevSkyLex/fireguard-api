<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Organization metadata and split legal profile into organization_legal_profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organizations ADD owner_user_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD slug VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('UPDATE organizations SET owner_user_id = created_by_user_id WHERE owner_user_id IS NULL');
        $this->addSql("UPDATE organizations SET status = CASE WHEN is_active THEN 'active' ELSE 'suspended' END WHERE status IS NULL");
        $this->addSql('UPDATE organizations SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql("UPDATE organizations SET slug = TRIM(BOTH '-' FROM LEFT(LOWER(TRIM(BOTH '-' FROM REGEXP_REPLACE(COALESCE(name, ''), '[^a-zA-Z0-9]+', '-', 'g'))) || '-' || SUBSTRING(id FROM 1 FOR 8), 120)) WHERE slug IS NULL");

        $this->addSql('ALTER TABLE organizations ALTER owner_user_id SET NOT NULL');
        $this->addSql('ALTER TABLE organizations ALTER slug SET NOT NULL');
        $this->addSql('ALTER TABLE organizations ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE organizations ALTER updated_at SET NOT NULL');

        $this->addSql("ALTER TABLE organizations ADD CONSTRAINT chk_organization_status CHECK (status IN ('active', 'suspended', 'archived'))");

        $this->addSql('CREATE UNIQUE INDEX uniq_organization_slug ON organizations (slug)');
        $this->addSql('CREATE INDEX idx_organization_owner_user ON organizations (owner_user_id)');
        $this->addSql('CREATE INDEX idx_organization_status ON organizations (status)');
        $this->addSql('CREATE INDEX idx_organization_status_name ON organizations (status, name)');
        $this->addSql('CREATE INDEX idx_organization_member_organization_active ON organization_members (organization_id, is_active)');
        $this->addSql('CREATE INDEX idx_organization_member_user_active ON organization_members (user_id, is_active)');

        $this->addSql("COMMENT ON COLUMN organizations.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE organization_legal_profile (organization_id VARCHAR(36) NOT NULL, legal_name VARCHAR(160) NOT NULL, registration_number VARCHAR(64) DEFAULT NULL, vat_number VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(organization_id))');
        $this->addSql("COMMENT ON COLUMN organization_legal_profile.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN organization_legal_profile.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE organization_legal_profile ADD CONSTRAINT fk_organization_legal_profile_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("INSERT INTO organization_legal_profile (organization_id, legal_name, registration_number, vat_number, created_at, updated_at) SELECT o.id, COALESCE(NULLIF(TRIM(o.name), ''), 'organization-' || SUBSTRING(o.id FROM 1 FOR 8)), NULL, NULL, o.created_at, o.updated_at FROM organizations o WHERE NOT EXISTS (SELECT 1 FROM organization_legal_profile p WHERE p.organization_id = o.id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_legal_profile DROP CONSTRAINT IF EXISTS fk_organization_legal_profile_organization');
        $this->addSql('DROP TABLE IF EXISTS organization_legal_profile');

        $this->addSql('DROP INDEX IF EXISTS idx_organization_member_user_active');
        $this->addSql('DROP INDEX IF EXISTS idx_organization_member_organization_active');
        $this->addSql('DROP INDEX IF EXISTS idx_organization_status_name');
        $this->addSql('DROP INDEX IF EXISTS idx_organization_status');
        $this->addSql('DROP INDEX IF EXISTS idx_organization_owner_user');
        $this->addSql('DROP INDEX IF EXISTS uniq_organization_slug');

        $this->addSql('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS chk_organization_status');

        $this->addSql('ALTER TABLE organizations DROP updated_at');
        $this->addSql('ALTER TABLE organizations DROP status');
        $this->addSql('ALTER TABLE organizations DROP slug');
        $this->addSql('ALTER TABLE organizations DROP owner_user_id');
    }
}
