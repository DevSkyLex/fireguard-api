<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260709130000.
 *
 * Adds the free-text `description` and the per-organization sequential
 * `number` to interventions, backfills existing rows in creation order, and
 * introduces the `intervention_number_counters` allocator table seeded from the
 * current maxima so future creations continue the sequence atomically.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260709130000 extends AbstractMigration
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
    return 'Add intervention description and per-organization number with counter table';
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
    $this->abortIf(
      'postgresql' !== $this->connection->getDatabasePlatform()->getName(),
      'This migration is intended for PostgreSQL only.',
    );

    $this->addSql('ALTER TABLE interventions ADD COLUMN description TEXT DEFAULT NULL');
    $this->addSql('ALTER TABLE interventions ADD COLUMN number INT DEFAULT NULL');
    $this->addSql(
      'UPDATE interventions AS i SET number = seq.rn FROM ('
      . 'SELECT id, ROW_NUMBER() OVER (PARTITION BY organization_id ORDER BY created_at, id) AS rn FROM interventions'
      . ') AS seq WHERE i.id = seq.id',
    );
    $this->addSql('ALTER TABLE interventions ALTER COLUMN number SET NOT NULL');
    $this->addSql('ALTER TABLE interventions ADD CONSTRAINT uniq_intervention_org_number UNIQUE (organization_id, number)');

    $this->addSql(
      'CREATE TABLE intervention_number_counters ('
      . 'organization_id VARCHAR(36) NOT NULL, last_number INT NOT NULL, PRIMARY KEY(organization_id))',
    );
    $this->addSql(
      'INSERT INTO intervention_number_counters (organization_id, last_number) '
      . 'SELECT organization_id, MAX(number) FROM interventions GROUP BY organization_id',
    );
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
    $this->abortIf(
      'postgresql' !== $this->connection->getDatabasePlatform()->getName(),
      'This migration is intended for PostgreSQL only.',
    );

    $this->addSql('DROP TABLE intervention_number_counters');
    $this->addSql('ALTER TABLE interventions DROP CONSTRAINT uniq_intervention_org_number');
    $this->addSql('ALTER TABLE interventions DROP COLUMN number');
    $this->addSql('ALTER TABLE interventions DROP COLUMN description');
  }
}
