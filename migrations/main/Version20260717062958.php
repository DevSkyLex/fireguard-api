<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260717062958.
 *
 * Introduces the Automation module's run log (`automation_runs`), unique
 * per `(rule_key, subject_id)` — the idempotence guard for automation rule
 * execution — backing the automation slice (Lot 7).
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260717062958 extends AbstractMigration
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
    return 'Add automation_runs table';
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

    $this->addSql(
      'CREATE TABLE automation_runs ('
      . 'id VARCHAR(36) NOT NULL, '
      . 'rule_key VARCHAR(80) NOT NULL, '
      . 'organization_id VARCHAR(36) NOT NULL, '
      . 'subject_id VARCHAR(36) NOT NULL, '
      . 'status VARCHAR(16) NOT NULL, '
      . 'intervention_id VARCHAR(36) DEFAULT NULL, '
      . 'error TEXT DEFAULT NULL, '
      . 'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'PRIMARY KEY(id))',
    );
    $this->addSql('CREATE INDEX idx_automation_run_organization ON automation_runs (organization_id)');
    $this->addSql('CREATE UNIQUE INDEX uniq_automation_run_rule_subject ON automation_runs (rule_key, subject_id)');
    $this->addSql('COMMENT ON COLUMN automation_runs.created_at IS \'(DC2Type:datetime_immutable)\'');
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

    $this->addSql('DROP TABLE automation_runs');
  }
}
