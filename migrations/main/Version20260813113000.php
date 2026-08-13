<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260813113000.
 *
 * Phase 5d.1 — activates the per-work-item attachment scope on
 * `intervention_attachments.work_item_id`. The column already exists
 * (added unused by `Version20260717111309`), but its foreign key was
 * dropped by `Version20260723201111` as drift because
 * `InterventionAttachmentRecord` mapped it as a plain column rather than an
 * ORM association — the schema-diff resync could not see it and removed it.
 * `InterventionAttachmentRecord::$workItem` is now a real `ManyToOne`
 * association (mirroring `InterventionChangeRecord::$workItem`), so this
 * migration re-adds the foreign key and its index, this time `ON DELETE
 * SET NULL`: deleting a work item must leave its attachments in place as
 * plain intervention-level evidence, not cascade-delete them (see
 * `src/Intervention/MODULE.md`).
 */
final class Version20260813113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5d.1: re-add intervention_attachments.work_item_id FK ON DELETE SET NULL and its index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_intervention_attachment_work_item ON intervention_attachments (work_item_id)');
        $this->addSql('ALTER TABLE intervention_attachments ADD CONSTRAINT fk_39377b11fa6e1a26 FOREIGN KEY (work_item_id) REFERENCES intervention_work_items (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE intervention_attachments DROP CONSTRAINT fk_39377b11fa6e1a26');
        $this->addSql('DROP INDEX idx_intervention_attachment_work_item');
    }
}
