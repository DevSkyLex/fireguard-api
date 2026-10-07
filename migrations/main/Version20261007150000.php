<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261007150000
 *
 * Widens retained aggregate totals without changing individual expense validation.
 *
 * @category Migration
 */
final class Version20261007150000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Preserve large exact published maintenance aggregate totals';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE maintenance_cost_snapshots ALTER total TYPE NUMERIC(38, 6), ALTER known_total TYPE NUMERIC(38, 6)');
  }

  public function down(Schema $schema): void
  {
    $this->abortIf(true, 'Published financial totals must not be truncated by reverting precision.');
  }
}
