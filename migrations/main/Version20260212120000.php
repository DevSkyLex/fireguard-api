<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add facilities table for organization-scoped site/building hierarchy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE facilities (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, parent_facility_id VARCHAR(36) DEFAULT NULL, type VARCHAR(24) NOT NULL, name VARCHAR(120) NOT NULL, code VARCHAR(80) DEFAULT NULL, status VARCHAR(20) NOT NULL, address VARCHAR(255) DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_facility_organization ON facilities (organization_id)');
        $this->addSql('CREATE INDEX idx_facility_parent ON facilities (parent_facility_id)');
        $this->addSql('CREATE INDEX idx_facility_type ON facilities (type)');
        $this->addSql('CREATE INDEX idx_facility_status ON facilities (status)');
        $this->addSql('CREATE INDEX idx_facility_organization_type ON facilities (organization_id, type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_facility_organization_code ON facilities (organization_id, code)');
        $this->addSql("COMMENT ON COLUMN facilities.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN facilities.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE facilities ADD CONSTRAINT fk_facility_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facilities ADD CONSTRAINT fk_facility_parent FOREIGN KEY (parent_facility_id) REFERENCES facilities (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facilities DROP CONSTRAINT fk_facility_organization');
        $this->addSql('ALTER TABLE facilities DROP CONSTRAINT fk_facility_parent');
        $this->addSql('DROP TABLE facilities');
    }
}
