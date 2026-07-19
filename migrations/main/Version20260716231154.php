<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260716231154.
 *
 * Introduces recurring intervention schedules (`intervention_recurrences`,
 * a rule — frequency, interval count, anchor date, in a fixed timezone —
 * against an existing organization template) and their materialization
 * attempts (`intervention_recurrence_runs`, unique per
 * `(recurrence_id, occurrence_date)` — the idempotence guard for the
 * recurring sweep), backing the recurring interventions slice (Lot 6).
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260716231154 extends AbstractMigration
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
    return 'Add intervention_recurrences and intervention_recurrence_runs tables';
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
      'CREATE TABLE intervention_recurrences ('
      . 'id VARCHAR(36) NOT NULL, '
      . 'organization_id VARCHAR(36) NOT NULL, '
      . 'template_id VARCHAR(36) NOT NULL, '
      . 'name VARCHAR(160) NOT NULL, '
      . 'site_id VARCHAR(36) DEFAULT NULL, '
      . 'responsible_id VARCHAR(36) DEFAULT NULL, '
      . 'frequency VARCHAR(16) NOT NULL, '
      . 'interval_count INT NOT NULL, '
      . 'anchor_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'rrule VARCHAR(255) DEFAULT NULL, '
      . 'timezone VARCHAR(64) NOT NULL, '
      . 'lead_time_days INT NOT NULL, '
      . 'next_occurrence_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'last_materialized_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, '
      . 'is_active BOOLEAN NOT NULL, '
      . 'end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, '
      . 'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'PRIMARY KEY(id))',
    );
    $this->addSql('CREATE INDEX idx_intervention_recurrence_organization ON intervention_recurrences (organization_id)');
    $this->addSql('CREATE INDEX idx_intervention_recurrence_due ON intervention_recurrences (is_active, next_occurrence_at)');
    $this->addSql('CREATE INDEX idx_intervention_recurrence_template ON intervention_recurrences (template_id)');
    $this->addSql(
      'ALTER TABLE intervention_recurrences ADD CONSTRAINT FK_intervention_recurrence_organization '
      . 'FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
    );
    $this->addSql(
      'ALTER TABLE intervention_recurrences ADD CONSTRAINT FK_intervention_recurrence_template '
      . 'FOREIGN KEY (template_id) REFERENCES intervention_templates (id) NOT DEFERRABLE INITIALLY IMMEDIATE',
    );
    $this->addSql('COMMENT ON COLUMN intervention_recurrences.anchor_date IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN intervention_recurrences.next_occurrence_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN intervention_recurrences.last_materialized_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN intervention_recurrences.end_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN intervention_recurrences.created_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN intervention_recurrences.updated_at IS \'(DC2Type:datetime_immutable)\'');

    $this->addSql(
      'CREATE TABLE intervention_recurrence_runs ('
      . 'id VARCHAR(36) NOT NULL, '
      . 'recurrence_id VARCHAR(36) NOT NULL, '
      . 'occurrence_date DATE NOT NULL, '
      . 'status VARCHAR(16) NOT NULL, '
      . 'intervention_id VARCHAR(36) DEFAULT NULL, '
      . 'error TEXT DEFAULT NULL, '
      . 'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'PRIMARY KEY(id))',
    );
    $this->addSql('CREATE INDEX idx_intervention_recurrence_run_recurrence ON intervention_recurrence_runs (recurrence_id)');
    $this->addSql(
      'ALTER TABLE intervention_recurrence_runs ADD CONSTRAINT uniq_intervention_recurrence_run_occurrence '
      . 'UNIQUE (recurrence_id, occurrence_date)',
    );
    $this->addSql(
      'ALTER TABLE intervention_recurrence_runs ADD CONSTRAINT FK_intervention_recurrence_run_recurrence '
      . 'FOREIGN KEY (recurrence_id) REFERENCES intervention_recurrences (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
    );
    $this->addSql('COMMENT ON COLUMN intervention_recurrence_runs.occurrence_date IS \'(DC2Type:date_immutable)\'');
    $this->addSql('COMMENT ON COLUMN intervention_recurrence_runs.created_at IS \'(DC2Type:datetime_immutable)\'');
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

    $this->addSql('DROP TABLE intervention_recurrence_runs');
    $this->addSql('DROP TABLE intervention_recurrences');
  }
}
