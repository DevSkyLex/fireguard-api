<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260811120000.
 *
 * Adds the nullable, indexed "organization_id" column to "audit_events" and
 * backfills it from metadata->>'organization_id'. The metadata copy stays the
 * hash-covered source of truth; the column is a denormalized filter for
 * organization-scoped audit reads (the events whose subjectId is not the
 * organization itself are otherwise unfilterable without a JSON scan).
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260811120000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the get description result
   */
  public function getDescription(): string
  {
    return 'Add indexed audit_events.organization_id, backfilled from metadata->>organization_id';
  }

  /**
   * Method up.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE audit_events ADD organization_id VARCHAR(64) DEFAULT NULL');
    // The backfill is deliberately NOT wrapped in LEFT(…, 64). Truncating a
    // source value to fit the column would silently store something that
    // differs from the hash-covered metadata the column is supposed to be
    // derived from — and "provably derived from a hash-covered field" is the
    // entire justification for keeping organization_id out of the event-hash
    // payload. A value longer than the column must abort this migration
    // loudly (Postgres 22001, "value too long for type character varying")
    // rather than be quietly reshaped into a divergent copy. Unreachable
    // with 36-character UUIDs; the point is that it stays unreachable
    // instead of becoming invisible.
    $this->addSql(<<<'SQL'
      UPDATE audit_events
      SET organization_id = metadata->>'organization_id'
      WHERE metadata->>'organization_id' IS NOT NULL
        AND metadata->>'organization_id' <> ''
      SQL);
    $this->addSql('CREATE INDEX idx_audit_organization_id ON audit_events (organization_id)');
  }

  /**
   * Method down.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP INDEX idx_audit_organization_id');
    $this->addSql('ALTER TABLE audit_events DROP organization_id');
  }
}
