<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Class Version20261006113002. Align immutable finance dates without rewriting captured facts. @category Migration */
final class Version20261006113002 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Annotate immutable finance dates to align PostgreSQL and Doctrine mapping.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql("COMMENT ON COLUMN maintenance_cost_hourly_rates.effective_from IS '(DC2Type:date_immutable)'");
    $this->addSql("COMMENT ON COLUMN maintenance_cost_expenses.incurred_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN maintenance_cost_expenses.created_at IS '(DC2Type:datetime_immutable)'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('COMMENT ON COLUMN maintenance_cost_hourly_rates.effective_from IS NULL');
    $this->addSql('COMMENT ON COLUMN maintenance_cost_expenses.incurred_at IS NULL');
    $this->addSql('COMMENT ON COLUMN maintenance_cost_expenses.created_at IS NULL');
  }
}
