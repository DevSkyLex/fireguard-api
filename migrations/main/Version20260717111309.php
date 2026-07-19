<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260717111309.
 *
 * R11b — generalized per-module attachments. Adds three main-DB tables
 * mirroring `equipment_attachments`: `facility_attachments`,
 * `inspection_attachments` (single table serving both inspection-level
 * documents and non-conformity field-proof photos via the nullable
 * `non_conformity_id` discriminator — see `src/Inspection/MODULE.md`) and
 * `intervention_attachments` (with a reserved, unused `work_item_id`
 * nullable FK for a future optional per-work-item attach scope — see
 * `src/Intervention/MODULE.md`).
 */
final class Version20260717111309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R11b: add facility_attachments, inspection_attachments and intervention_attachments tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE facility_attachments (id VARCHAR(36) NOT NULL, facility_id VARCHAR(36) NOT NULL, file_name VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL, label VARCHAR(255) DEFAULT NULL, revision INT NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_facility_attachment_facility ON facility_attachments (facility_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_facility_attachment_storage_path ON facility_attachments (storage_path)');
        $this->addSql('COMMENT ON COLUMN facility_attachments.uploaded_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE inspection_attachments (id VARCHAR(36) NOT NULL, inspection_id VARCHAR(36) NOT NULL, non_conformity_id VARCHAR(36) DEFAULT NULL, file_name VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL, label VARCHAR(255) DEFAULT NULL, revision INT NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_inspection_attachment_inspection ON inspection_attachments (inspection_id)');
        $this->addSql('CREATE INDEX idx_inspection_attachment_non_conformity ON inspection_attachments (non_conformity_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_inspection_attachment_storage_path ON inspection_attachments (storage_path)');
        $this->addSql('COMMENT ON COLUMN inspection_attachments.uploaded_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE intervention_attachments (id VARCHAR(36) NOT NULL, intervention_id VARCHAR(36) NOT NULL, work_item_id VARCHAR(36) DEFAULT NULL, file_name VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL, label VARCHAR(255) DEFAULT NULL, revision INT NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_intervention_attachment_intervention ON intervention_attachments (intervention_id)');
        $this->addSql('CREATE INDEX idx_intervention_attachment_work_item ON intervention_attachments (work_item_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_intervention_attachment_storage_path ON intervention_attachments (storage_path)');
        $this->addSql('COMMENT ON COLUMN intervention_attachments.uploaded_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE facility_attachments ADD CONSTRAINT FK_1A3E276A7014910 FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inspection_attachments ADD CONSTRAINT FK_BC75FA20F02F2DDF FOREIGN KEY (inspection_id) REFERENCES inspections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inspection_attachments ADD CONSTRAINT FK_BC75FA208EA30491 FOREIGN KEY (non_conformity_id) REFERENCES non_conformities (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE intervention_attachments ADD CONSTRAINT FK_39377B118EAE3863 FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // work_item_id is a reserved, currently-unused column (see class docblock): the FK is
        // added directly here since the Record maps it as a plain column, not an ORM association.
        $this->addSql('ALTER TABLE intervention_attachments ADD CONSTRAINT FK_39377B11FA6E1A26 FOREIGN KEY (work_item_id) REFERENCES intervention_work_items (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facility_attachments DROP CONSTRAINT FK_1A3E276A7014910');
        $this->addSql('ALTER TABLE inspection_attachments DROP CONSTRAINT FK_BC75FA20F02F2DDF');
        $this->addSql('ALTER TABLE inspection_attachments DROP CONSTRAINT FK_BC75FA208EA30491');
        $this->addSql('ALTER TABLE intervention_attachments DROP CONSTRAINT FK_39377B118EAE3863');
        $this->addSql('ALTER TABLE intervention_attachments DROP CONSTRAINT FK_39377B11FA6E1A26');

        $this->addSql('DROP TABLE facility_attachments');
        $this->addSql('DROP TABLE inspection_attachments');
        $this->addSql('DROP TABLE intervention_attachments');
    }
}
