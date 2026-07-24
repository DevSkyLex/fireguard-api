<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619120000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add the organizations.settings JSON column for structured organization preferences (notifications, regional)';
  }

  public function up(Schema $schema): void
  {
    $this->addSql("ALTER TABLE organizations ADD settings JSON DEFAULT '{}' NOT NULL");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE organizations DROP settings');
  }
}
