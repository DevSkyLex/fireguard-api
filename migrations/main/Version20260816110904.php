<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Facility plan Phase 3 — floor plan attachments. Adds `kind`,
 * `is_primary_plan`, `image_width` and `image_height` to
 * `facility_attachments`, plus a partial unique index enforcing at most one
 * `is_primary_plan = true` row per facility — the same "Doctrine's ORM
 * attributes cannot express a partial index" precedent as
 * `uniq_intervention_attachment_signature` (see `Version20260813140000`),
 * hand-written raw SQL rather than an `#[ORM\UniqueConstraint]` on
 * `FacilityAttachmentRecord`.
 *
 * `FacilityAttachmentRepository::clearPrimaryPlan()` /
 * `SetPrimaryFacilityAttachmentHandler` clear the previous primary inside
 * the same transaction as the promotion, so this index should never actually
 * reject a legitimate write; it exists as the schema-level backstop against
 * a bug or a concurrent race doing the two writes out of order.
 */
final class Version20260816110904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facility plan Phase 3: add kind, is_primary_plan, image_width, image_height to facility_attachments, and a partial unique index enforcing at most one primary plan per facility.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE facility_attachments ADD kind VARCHAR(20) NOT NULL DEFAULT 'document'");
        $this->addSql('ALTER TABLE facility_attachments ADD is_primary_plan BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE facility_attachments ADD image_width INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facility_attachments ADD image_height INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_facility_attachment_primary_plan ON facility_attachments (facility_id) WHERE (is_primary_plan)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_facility_attachment_primary_plan');
        $this->addSql('ALTER TABLE facility_attachments DROP kind');
        $this->addSql('ALTER TABLE facility_attachments DROP is_primary_plan');
        $this->addSql('ALTER TABLE facility_attachments DROP image_width');
        $this->addSql('ALTER TABLE facility_attachments DROP image_height');
    }
}
