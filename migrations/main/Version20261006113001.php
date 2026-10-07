<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Add private organization currency and append-only exact hourly rate parameters. */
final class Version20261006113001 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add lockable maintenance-cost currency and effective organization member hourly rates.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql("CREATE TABLE maintenance_cost_currency_settings (organization_id VARCHAR(36) NOT NULL, currency VARCHAR(3) DEFAULT 'EUR' NOT NULL, locked BOOLEAN DEFAULT FALSE NOT NULL, PRIMARY KEY(organization_id))");
    $this->addSql('CREATE TABLE maintenance_cost_hourly_rates (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, client_id VARCHAR(36) NOT NULL, hourly_amount NUMERIC(24, 6) NOT NULL, effective_from DATE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_cost_rate_client ON maintenance_cost_hourly_rates (organization_id, client_id)');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_cost_rate_effective ON maintenance_cost_hourly_rates (organization_id, member_id, effective_from)');
    $this->addSql('CREATE INDEX idx_maintenance_cost_rate_member ON maintenance_cost_hourly_rates (organization_id, member_id)');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE maintenance_cost_hourly_rates');
    $this->addSql('DROP TABLE maintenance_cost_currency_settings');
  }
}
