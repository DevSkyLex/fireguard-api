<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the maintenance_schedules table (Maintenance module).
 */
final class Version20260716213501 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Create maintenance_schedules table (preventive-maintenance scheduling)';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE maintenance_schedules (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, equipment_id VARCHAR(36) NOT NULL, facility_id VARCHAR(36) DEFAULT NULL, equipment_type VARCHAR(32) NOT NULL, interval_override VARCHAR(32) DEFAULT NULL, last_inspection_closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, next_due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, due_status VARCHAR(16) NOT NULL, last_reminded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, reminded_for TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX IDX_74FAF8FF32C8A3DE ON maintenance_schedules (organization_id)');
    $this->addSql('CREATE INDEX idx_maintenance_schedule_org_status_due ON maintenance_schedules (organization_id, due_status, next_due_at)');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_schedule_org_equipment ON maintenance_schedules (organization_id, equipment_id)');
    $this->addSql('COMMENT ON COLUMN maintenance_schedules.last_inspection_closed_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN maintenance_schedules.next_due_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN maintenance_schedules.last_reminded_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN maintenance_schedules.reminded_for IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN maintenance_schedules.created_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN maintenance_schedules.updated_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('ALTER TABLE maintenance_schedules ADD CONSTRAINT FK_74FAF8FF32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE maintenance_schedules DROP CONSTRAINT FK_74FAF8FF32C8A3DE');
    $this->addSql('DROP TABLE maintenance_schedules');
  }
}
