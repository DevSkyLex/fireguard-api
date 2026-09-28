<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Normalizes legacy combined presence modes while preserving invisibility. */
final class Version20260926130000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Make existing Invisible and Do Not Disturb preferences mutually exclusive';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('UPDATE user_presence_preferences SET do_not_disturb = false, revision = revision + 1 WHERE invisible = true AND do_not_disturb = true');
  }

  public function down(Schema $schema): void
  {
    // Previous NPD choices cannot be inferred after users have changed modes.
    $this->throwIrreversibleMigrationException('Previous combined presence preferences cannot be restored.');
  }
}
