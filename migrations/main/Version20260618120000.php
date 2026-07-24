<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organization description and logo_url columns and drop the organization_legal_profile table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organizations ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD logo_url VARCHAR(500) DEFAULT NULL');

        $this->addSql('ALTER TABLE organization_legal_profile DROP CONSTRAINT IF EXISTS fk_organization_legal_profile_organization');
        $this->addSql('DROP TABLE IF EXISTS organization_legal_profile');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_legal_profile (organization_id VARCHAR(36) NOT NULL, legal_name VARCHAR(160) NOT NULL, registration_number VARCHAR(64) DEFAULT NULL, vat_number VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, legal_type VARCHAR(32) NOT NULL, country_code VARCHAR(2) NOT NULL, PRIMARY KEY(organization_id))');
        $this->addSql("COMMENT ON COLUMN organization_legal_profile.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN organization_legal_profile.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE organization_legal_profile ADD CONSTRAINT chk_organization_legal_profile_legal_type CHECK (legal_type IN ('company', 'non_profit', 'public_sector', 'individual', 'other'))");
        $this->addSql("ALTER TABLE organization_legal_profile ADD CONSTRAINT chk_organization_legal_profile_country_code CHECK (country_code ~ '^[A-Z]{2}$')");
        $this->addSql('ALTER TABLE organization_legal_profile ADD CONSTRAINT fk_organization_legal_profile_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE organizations DROP logo_url');
        $this->addSql('ALTER TABLE organizations DROP description');
    }
}
