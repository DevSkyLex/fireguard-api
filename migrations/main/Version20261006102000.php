<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Additive main-database migration; legacy schedules remain intact. */
final class Version20261006102000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add multi-operation maintenance plans, stable occurrences and engine authority.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE maintenance_plans (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, equipment_id VARCHAR(36) NOT NULL, facility_id VARCHAR(36) DEFAULT NULL, equipment_type VARCHAR(64) NOT NULL, name VARCHAR(160) NOT NULL, operation_kind VARCHAR(16) NOT NULL, interval VARCHAR(32) NOT NULL, cadence_mode VARCHAR(16) NOT NULL, anchor_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, next_due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, active BOOLEAN NOT NULL, legacy_schedule_id VARCHAR(36) DEFAULT NULL, last_completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_plan_legacy_schedule ON maintenance_plans (legacy_schedule_id)');
    $this->addSql('CREATE INDEX idx_maintenance_plan_org_due ON maintenance_plans (organization_id, active, next_due_at)');
    $this->addSql('CREATE TABLE maintenance_occurrences (id VARCHAR(36) NOT NULL, plan_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, due_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(16) NOT NULL, attempt INT NOT NULL, intervention_id VARCHAR(36) DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, result_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql("CREATE UNIQUE INDEX uniq_maintenance_occurrence_open ON maintenance_occurrences (plan_id) WHERE status = 'open'");
    $this->addSql('CREATE INDEX idx_maintenance_occurrence_org_plan ON maintenance_occurrences (organization_id, plan_id)');
    $this->addSql('ALTER TABLE maintenance_occurrences ADD number INT DEFAULT NULL');
    $this->addSql('CREATE TABLE maintenance_engines (organization_id VARCHAR(36) NOT NULL, mode VARCHAR(16) NOT NULL, activated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(organization_id))');
    $this->addSql('CREATE TABLE maintenance_operation_receipts (id VARCHAR(36) NOT NULL, occurrence_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, outcome VARCHAR(16) NOT NULL, performed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE maintenance_operation_receipts');
    $this->addSql('DROP TABLE maintenance_engines');
    $this->addSql('DROP TABLE maintenance_occurrences');
    $this->addSql('DROP TABLE maintenance_plans');
  }
}
