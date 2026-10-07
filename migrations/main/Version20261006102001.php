<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Persist the original local calendar separately from UTC storage instants. */
final class Version20261006102001 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Preserve maintenance plan calendar timezone across month-end and DST transitions.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql("ALTER TABLE maintenance_plans ADD calendar_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE maintenance_plans DROP calendar_timezone');
  }
}
