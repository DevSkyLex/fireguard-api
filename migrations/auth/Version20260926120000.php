<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds the private account visibility preference to the auth database. */
final class Version20260926120000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add invisible mode to user presence preferences';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE user_presence_preferences ADD invisible BOOLEAN DEFAULT false NOT NULL');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE user_presence_preferences DROP invisible');
  }
}
