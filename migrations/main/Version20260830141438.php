<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260830141438.
 *
 * Adds `level_index` to `facilities` — the optional stacking order of a
 * floor (ground floor = 0, first basement = -1, and so on) that backs the
 * 3D building view (lot A1). Existing rows are left `NULL` on purpose: no
 * backfill, floors without a known level simply sort after the ones that
 * have one. Also creates the composite index `idx_facility_parent_level`
 * on `(parent_facility_id, level_index)`, which the diff cannot express on
 * its own, to support sorting a parent's floors by level. The
 * auto-generated cross-database noise (a DROP of every auth-side table
 * visible to the diff) was hand-trimmed, as in every main migration.
 */
final class Version20260830141438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add facilities.level_index and the idx_facility_parent_level composite index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facilities ADD level_index INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_facility_parent_level ON facilities (parent_facility_id, level_index)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_facility_parent_level');
        $this->addSql('ALTER TABLE facilities DROP level_index');
    }
}
