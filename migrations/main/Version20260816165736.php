<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates `facility_metadata_fields`: an organization-defined typed schema
 * for facility metadata (key/label/fieldType/options/facilityType/required/
 * unit), unique per organization by machine key.
 */
final class Version20260816165736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create facility_metadata_fields (organization-defined typed facility metadata schema).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE facility_metadata_fields (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, field_key VARCHAR(64) NOT NULL, label VARCHAR(80) NOT NULL, field_type VARCHAR(16) NOT NULL, options JSON NOT NULL, facility_type VARCHAR(24) DEFAULT NULL, required BOOLEAN DEFAULT false NOT NULL, unit VARCHAR(16) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_facility_metadata_field_organization ON facility_metadata_fields (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_facility_metadata_field_organization_key ON facility_metadata_fields (organization_id, field_key)');
        $this->addSql('COMMENT ON COLUMN facility_metadata_fields.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN facility_metadata_fields.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE facility_metadata_fields ADD CONSTRAINT FK_37E6724C32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facility_metadata_fields DROP CONSTRAINT FK_37E6724C32C8A3DE');
        $this->addSql('DROP TABLE facility_metadata_fields');
    }
}
