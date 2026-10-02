<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002031000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Retain notification inbox identity and per-channel terminal delivery receipts.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE notification_delivery_receipts (id VARCHAR(64) NOT NULL, notification_id VARCHAR(36) NOT NULL, channel_status JSONB NOT NULL, PRIMARY KEY(id))');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE notification_delivery_receipts');
  }
}
