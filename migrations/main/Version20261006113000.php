<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Class Version20261006113000. Additive private maintenance finance and publication snapshots. @category Migration */
final class Version20261006113000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add exact external expenses, independently versioned planning and immutable private publication costs.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE maintenance_cost_expenses (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, intervention_id VARCHAR(36) NOT NULL, work_item_id VARCHAR(36) DEFAULT NULL, client_id VARCHAR(64) NOT NULL, amount NUMERIC(24,6) NOT NULL, currency VARCHAR(3) NOT NULL, description TEXT NOT NULL, incurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, adjustment_of VARCHAR(36) DEFAULT NULL, created_by VARCHAR(36) NOT NULL, payload_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_expense_client ON maintenance_cost_expenses (organization_id, client_id)');
    $this->addSql('CREATE INDEX idx_maintenance_expense_intervention ON maintenance_cost_expenses (organization_id, intervention_id)');
    $this->addSql('CREATE TABLE maintenance_cost_planning (organization_id VARCHAR(36) NOT NULL, intervention_id VARCHAR(36) NOT NULL, planned_budget NUMERIC(24,6) DEFAULT NULL, estimated_minutes INT DEFAULT NULL, resources JSON NOT NULL, revision INT NOT NULL, PRIMARY KEY(organization_id, intervention_id))');
    $this->addSql('CREATE TABLE maintenance_cost_snapshots (organization_id VARCHAR(36) NOT NULL, intervention_id VARCHAR(36) NOT NULL, publication_id VARCHAR(36) NOT NULL, intervention_revision INT NOT NULL, captured_at VARCHAR(40) NOT NULL, version INT NOT NULL, currency VARCHAR(3) NOT NULL, total NUMERIC(24,6) DEFAULT NULL, known_total NUMERIC(24,6) NOT NULL, complete BOOLEAN NOT NULL, planned_budget NUMERIC(24,6) DEFAULT NULL, estimated_minutes INT DEFAULT NULL, planning_revision INT NOT NULL, planning_resources JSON NOT NULL, items JSON NOT NULL, PRIMARY KEY(organization_id, intervention_id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_cost_publication ON maintenance_cost_snapshots (publication_id)');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE maintenance_cost_snapshots');
    $this->addSql('DROP TABLE maintenance_cost_planning');
    $this->addSql('DROP TABLE maintenance_cost_expenses');
  }
}
