<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260828000000.
 *
 * Creates `compliance_register_snapshots` — the append-only archive of the
 * regulatory "registre de sécurité" PDF exports (Compliance module). Each
 * row stores the snapshot metadata only (scope, generation datetime, actor,
 * SHA-256 content hash, byte size, file-storage path); the PDF bytes live in
 * file storage under `compliance/registers/<organizationId>/<snapshotId>.pdf`.
 * `organization_id`/`facility_id` are plain identifier columns, mirroring
 * `import_jobs.organization_id`'s precedent.
 */
final class Version20260828000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the compliance_register_snapshots table archiving dated safety register PDF exports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE compliance_register_snapshots (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, facility_id VARCHAR(36) DEFAULT NULL, generated_at VARCHAR(64) NOT NULL, generated_by_user_id VARCHAR(36) NOT NULL, content_hash VARCHAR(64) NOT NULL, size_bytes INT NOT NULL, storage_path VARCHAR(512) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_register_snapshot_org_generated ON compliance_register_snapshots (organization_id, generated_at)');
        $this->addSql('COMMENT ON COLUMN compliance_register_snapshots.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE compliance_register_snapshots');
    }
}
