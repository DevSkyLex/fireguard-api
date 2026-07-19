<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

use function sprintf;

/**
 * Migration Version20260717150000.
 *
 * R10-index — trigram search indexes (main DB, PostgreSQL only).
 *
 * The R10-code lot pushed free-text search down into SQL as
 * `LOWER(col) LIKE :term` predicates (Shared TrigramSearchExpression) across
 * the Equipment / Facility / Inspection / Intervention list queries. Those
 * `LIKE '%…%'` predicates cannot use a btree index; this migration adds the
 * matching `gin (LOWER(col) gin_trgm_ops)` expression indexes so each OR
 * branch is index-backed (Postgres BitmapOr).
 *
 * Deliberately authored BY HAND and applied LAST in Phase 2: GIN trigram
 * expression indexes cannot be expressed via ORM `#[ORM\Index]`, so they are
 * invisible to Doctrine metadata and a future `doctrine:migrations:diff`
 * would emit a spurious `DROP INDEX` for each. Never blind-apply a generated
 * diff after this — the `*_trgm` indexes live only here.
 *
 * PostgreSQL-only: guarded with `skipIf` so it never emits `CREATE EXTENSION
 * pg_trgm` / `USING gin` against the SQLite test databases (which build their
 * schema from ORM metadata via SchemaTool, not from migrations). Non
 * transactional because `CREATE INDEX CONCURRENTLY` cannot run inside a
 * transaction; every statement is idempotent (`IF NOT EXISTS`) so a partial
 * run is safe to re-apply.
 */
final class Version20260717150000 extends AbstractMigration
{
  /**
   * Searched columns per table, aligned EXACTLY with the column expressions
   * passed to TrigramSearchExpression::apply() in each repository. Keep in
   * sync when a repository's free-text OR changes.
   *
   * @var array<string, list<string>>
   */
  private const array TRIGRAM_COLUMNS = [
    'equipment' => ['type', 'sub_type', 'brand', 'model', 'serial_number', 'status', 'location_label'],
    'facilities' => ['name', 'type', 'code', 'status', 'address'],
    'inspections' => ['result', 'status', 'inspector_name', 'equipment_id', 'facility_id', 'checklist_id'],
    'interventions' => ['name'],
  ];

  public function getDescription(): string
  {
    return 'R10-index: pg_trgm GIN trigram indexes on the free-text search columns (PostgreSQL only).';
  }

  public function isTransactional(): bool
  {
    return false;
  }

  public function up(Schema $schema): void
  {
    $this->skipIf(
      !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
      'pg_trgm trigram search indexes are PostgreSQL-only.',
    );

    $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

    foreach (self::TRIGRAM_COLUMNS as $table => $columns) {
      foreach ($columns as $column) {
        $this->addSql(sprintf(
          'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_%s_%s_trgm ON %s USING gin (LOWER(%s) gin_trgm_ops)',
          $table,
          $column,
          $table,
          $column,
        ));
      }
    }
  }

  public function down(Schema $schema): void
  {
    $this->skipIf(
      !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
      'pg_trgm trigram search indexes are PostgreSQL-only.',
    );

    foreach (self::TRIGRAM_COLUMNS as $table => $columns) {
      foreach ($columns as $column) {
        $this->addSql(sprintf('DROP INDEX CONCURRENTLY IF EXISTS idx_%s_%s_trgm', $table, $column));
      }
    }

    // The pg_trgm extension is intentionally left installed (it may back other
    // indexes and is harmless to keep).
  }
}
