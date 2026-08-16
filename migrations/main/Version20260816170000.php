<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260816170000.
 *
 * Bulk CSV import v2 — dry-run mode. Adds `import_jobs.is_dry_run` (main
 * DB): a dry-run job runs the entire pipeline (parsing, per-row validation,
 * parent-by-code resolution, quota projection) without persisting anything,
 * and its report reuses the existing `error_report` column, now also
 * carrying `would_create` entries for a dry run's successful rows.
 * Backfilled `false` for every existing (real) import job.
 */
final class Version20260816170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R14: add import_jobs.is_dry_run (bulk CSV import v2 — dry-run mode).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_jobs ADD is_dry_run BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_jobs DROP is_dry_run');
    }
}
