<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920193000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Maintenance evaluation freshness and date-filter index; legacy evaluations remain unknown';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE maintenance_schedules ADD evaluated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql("COMMENT ON COLUMN maintenance_schedules.evaluated_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql('CREATE INDEX idx_maintenance_schedule_org_due ON maintenance_schedules (organization_id, next_due_at)');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP INDEX idx_maintenance_schedule_org_due');
    $this->addSql('ALTER TABLE maintenance_schedules DROP evaluated_at');
  }
}
