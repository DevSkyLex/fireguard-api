<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921230000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Retain the single confirmed import for each successful CSV simulation.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE import_jobs ADD confirmed_job_id VARCHAR(36) DEFAULT NULL');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE import_jobs DROP confirmed_job_id');
  }
}
