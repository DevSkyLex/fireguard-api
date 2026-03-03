<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inspection module tables (inspections, non_conformities, checklists, checklist_items)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE inspections (
                id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                equipment_id VARCHAR(36) NOT NULL,
                facility_id VARCHAR(36) DEFAULT NULL,
                inspector_type VARCHAR(16) NOT NULL,
                inspector_name VARCHAR(255) NOT NULL,
                inspector_user_id VARCHAR(36) DEFAULT NULL,
                inspector_organization_name VARCHAR(255) DEFAULT NULL,
                result VARCHAR(16) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                performed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                checklist_id VARCHAR(36) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                signature TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql('CREATE INDEX idx_inspection_organization ON inspections (organization_id)');
        $this->addSql('CREATE INDEX idx_inspection_equipment ON inspections (equipment_id)');
        $this->addSql('CREATE INDEX idx_inspection_facility ON inspections (facility_id)');
        $this->addSql('CREATE INDEX idx_inspection_result ON inspections (result)');
        $this->addSql('CREATE INDEX idx_inspection_status ON inspections (status)');
        $this->addSql('CREATE INDEX idx_inspection_organization_equipment ON inspections (organization_id, equipment_id)');
        $this->addSql('CREATE INDEX idx_inspection_organization_result ON inspections (organization_id, result)');
        $this->addSql('CREATE INDEX idx_inspection_organization_status ON inspections (organization_id, status)');

        $this->addSql("
            CREATE TABLE non_conformities (
                id VARCHAR(36) NOT NULL,
                inspection_id VARCHAR(36) NOT NULL,
                description TEXT NOT NULL,
                severity VARCHAR(16) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'open',
                due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql('CREATE INDEX idx_non_conformity_inspection ON non_conformities (inspection_id)');
        $this->addSql('CREATE INDEX idx_non_conformity_severity ON non_conformities (severity)');
        $this->addSql('CREATE INDEX idx_non_conformity_status ON non_conformities (status)');
        $this->addSql('CREATE INDEX idx_non_conformity_inspection_severity ON non_conformities (inspection_id, severity)');
        $this->addSql('CREATE INDEX idx_non_conformity_inspection_status ON non_conformities (inspection_id, status)');

        $this->addSql("
            CREATE TABLE checklists (
                id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                version VARCHAR(50) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql('CREATE INDEX idx_checklist_organization ON checklists (organization_id)');
        $this->addSql('CREATE INDEX idx_checklist_status ON checklists (status)');
        $this->addSql('CREATE INDEX idx_checklist_organization_status ON checklists (organization_id, status)');

        $this->addSql("
            CREATE TABLE checklist_items (
                id VARCHAR(36) NOT NULL,
                checklist_id VARCHAR(36) NOT NULL,
                label VARCHAR(255) NOT NULL,
                position INTEGER NOT NULL,
                required BOOLEAN NOT NULL DEFAULT TRUE,
                description TEXT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql('CREATE INDEX idx_checklist_item_checklist ON checklist_items (checklist_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS checklist_items');
        $this->addSql('DROP TABLE IF EXISTS checklists');
        $this->addSql('DROP TABLE IF EXISTS non_conformities');
        $this->addSql('DROP TABLE IF EXISTS inspections');
    }
}
