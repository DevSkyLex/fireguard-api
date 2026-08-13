<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260813130500.
 *
 * Phase 5d.2 — adds the attachment `kind` column to `intervention_attachments`
 * (`file` | `signature`), the typed completion signature captured when the
 * responsible submits field work. `DEFAULT 'file'` backfills every existing
 * row in the same statement and keeps the column `NOT NULL` from the start;
 * the default is left in place afterward so it stays aligned with
 * `InterventionAttachmentKind::FILE` — the Domain model's own default.
 */
final class Version20260813130500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Phase 5d.2: add intervention_attachments.kind ('file'|'signature'), backfilling existing rows to 'file'.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE intervention_attachments ADD kind VARCHAR(20) NOT NULL DEFAULT 'file'");
        $this->addSql('CREATE INDEX idx_intervention_attachment_intervention_kind ON intervention_attachments (intervention_id, kind)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_intervention_attachment_intervention_kind');
        $this->addSql('ALTER TABLE intervention_attachments DROP kind');
    }
}
