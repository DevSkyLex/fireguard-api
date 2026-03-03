<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create equipment module tables (equipment, equipment_tag_catalog, equipment_tag, equipment_attachments)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE equipment (
                id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                facility_id VARCHAR(36) DEFAULT NULL,
                type VARCHAR(32) NOT NULL,
                sub_type VARCHAR(100) DEFAULT NULL,
                brand VARCHAR(100) DEFAULT NULL,
                model VARCHAR(100) DEFAULT NULL,
                serial_number VARCHAR(100) DEFAULT NULL,
                location_label VARCHAR(255) DEFAULT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'in_stock',
                installed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                commissioned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql('CREATE INDEX idx_equipment_organization ON equipment (organization_id)');
        $this->addSql('CREATE INDEX idx_equipment_facility ON equipment (facility_id)');
        $this->addSql('CREATE INDEX idx_equipment_type ON equipment (type)');
        $this->addSql('CREATE INDEX idx_equipment_status ON equipment (status)');
        $this->addSql('CREATE INDEX idx_equipment_organization_type ON equipment (organization_id, type)');
        $this->addSql('CREATE INDEX idx_equipment_organization_status ON equipment (organization_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_equipment_organization_serial ON equipment (organization_id, serial_number) WHERE serial_number IS NOT NULL');

        $this->addSql('
            CREATE TABLE equipment_tag_catalog (
                id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ');

        $this->addSql('CREATE INDEX idx_tag_organization ON equipment_tag_catalog (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_organization_name ON equipment_tag_catalog (organization_id, name)');

        $this->addSql('
            CREATE TABLE equipment_tag (
                equipment_id VARCHAR(36) NOT NULL,
                tag_id VARCHAR(36) NOT NULL,
                PRIMARY KEY(equipment_id, tag_id)
            )
        ');

        $this->addSql('CREATE INDEX idx_equipment_tag_equipment ON equipment_tag (equipment_id)');

        $this->addSql('
            CREATE TABLE equipment_attachments (
                id VARCHAR(36) NOT NULL,
                equipment_id VARCHAR(36) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                size BIGINT NOT NULL,
                label VARCHAR(255) DEFAULT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ');

        $this->addSql('CREATE INDEX idx_attachment_equipment ON equipment_attachments (equipment_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_attachment_storage_path ON equipment_attachments (storage_path)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS equipment_attachments');
        $this->addSql('DROP TABLE IF EXISTS equipment_tag');
        $this->addSql('DROP TABLE IF EXISTS equipment_tag_catalog');
        $this->addSql('DROP TABLE IF EXISTS equipment');
    }
}
