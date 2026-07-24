<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260717075344.
 *
 * Adds the R12 intervention service-history columns to
 * `equipment_maintenance_logs` (additive, no new table): a completed,
 * point-in-time entry synthesized from a published intervention's applied
 * equipment change (`source = 'intervention'`) alongside the existing
 * status-transition windows (`source = 'status_transition'`, the new
 * column's default). `dedup_key` carries a unique index guarding the
 * raw-DBAL idempotent insert
 * (`Equipment\Infrastructure\Persistence\Doctrine\Repository\MaintenanceLogRepository::appendInterventionServiceEntry`)
 * against at-least-once event redelivery.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260717075344 extends AbstractMigration
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
    return 'Add intervention service-history columns + dedup index to equipment_maintenance_logs';
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

    $this->addSql('ALTER TABLE equipment_maintenance_logs ADD source VARCHAR(32) DEFAULT \'status_transition\' NOT NULL');
    $this->addSql('ALTER TABLE equipment_maintenance_logs ADD intervention_id VARCHAR(36) DEFAULT NULL');
    $this->addSql('ALTER TABLE equipment_maintenance_logs ADD intervention_number INT DEFAULT NULL');
    $this->addSql('ALTER TABLE equipment_maintenance_logs ADD work_item_action VARCHAR(64) DEFAULT NULL');
    $this->addSql('ALTER TABLE equipment_maintenance_logs ADD actor_id VARCHAR(36) DEFAULT NULL');
    $this->addSql('ALTER TABLE equipment_maintenance_logs ADD summary TEXT DEFAULT NULL');
    $this->addSql('ALTER TABLE equipment_maintenance_logs ADD dedup_key VARCHAR(64) DEFAULT NULL');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_log_dedup ON equipment_maintenance_logs (dedup_key)');
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

    $this->addSql('DROP INDEX uniq_maintenance_log_dedup');
    $this->addSql('ALTER TABLE equipment_maintenance_logs DROP source');
    $this->addSql('ALTER TABLE equipment_maintenance_logs DROP intervention_id');
    $this->addSql('ALTER TABLE equipment_maintenance_logs DROP intervention_number');
    $this->addSql('ALTER TABLE equipment_maintenance_logs DROP work_item_action');
    $this->addSql('ALTER TABLE equipment_maintenance_logs DROP actor_id');
    $this->addSql('ALTER TABLE equipment_maintenance_logs DROP summary');
    $this->addSql('ALTER TABLE equipment_maintenance_logs DROP dedup_key');
  }
}
