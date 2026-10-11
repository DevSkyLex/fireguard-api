<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Preserve retry-cycle ownership when a stale Messenger envelope exhausts its budget. */
final class Version20261011120001 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Fence webhook technical quarantine to the owning wake/retry cycle.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE webhook_deliveries ADD command_token VARCHAR(36) DEFAULT NULL');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE webhook_deliveries DROP command_token');
  }
}
