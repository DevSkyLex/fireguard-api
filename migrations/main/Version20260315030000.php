<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align immutable datetime columns in main schema with Doctrine metadata';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('ALTER TABLE equipment_tag_catalog ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("COMMENT ON COLUMN equipment_tag_catalog.created_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE equipment_attachments ALTER uploaded_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("COMMENT ON COLUMN equipment_attachments.uploaded_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE equipment ALTER installed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE equipment ALTER commissioned_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE equipment ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE equipment ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("COMMENT ON COLUMN equipment.installed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN equipment.commissioned_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN equipment.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN equipment.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE checklists ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE checklists ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("COMMENT ON COLUMN checklists.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN checklists.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE non_conformities ALTER due_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE non_conformities ALTER resolved_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE non_conformities ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE non_conformities ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("COMMENT ON COLUMN non_conformities.due_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN non_conformities.resolved_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN non_conformities.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN non_conformities.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE inspections ALTER performed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE inspections ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE inspections ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("COMMENT ON COLUMN inspections.performed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN inspections.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN inspections.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('COMMENT ON COLUMN equipment_tag_catalog.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN equipment_attachments.uploaded_at IS NULL');

        $this->addSql('COMMENT ON COLUMN equipment.installed_at IS NULL');
        $this->addSql('COMMENT ON COLUMN equipment.commissioned_at IS NULL');
        $this->addSql('COMMENT ON COLUMN equipment.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN equipment.updated_at IS NULL');

        $this->addSql('COMMENT ON COLUMN checklists.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN checklists.updated_at IS NULL');

        $this->addSql('COMMENT ON COLUMN non_conformities.due_at IS NULL');
        $this->addSql('COMMENT ON COLUMN non_conformities.resolved_at IS NULL');
        $this->addSql('COMMENT ON COLUMN non_conformities.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN non_conformities.updated_at IS NULL');

        $this->addSql('COMMENT ON COLUMN inspections.performed_at IS NULL');
        $this->addSql('COMMENT ON COLUMN inspections.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN inspections.updated_at IS NULL');
    }
}
