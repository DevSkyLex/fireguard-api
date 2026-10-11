<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

use function is_numeric;

/** Adds the nullable immutable avatar pointer before new producers are enabled.
 * @category Migration
 */
final class Version20261011120000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Track immutable avatar generations while preserving historical canonical avatars';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE users ADD avatar_storage_key VARCHAR(36) DEFAULT NULL');
  }

  public function down(Schema $schema): void
  {
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE avatar_storage_key IS NOT NULL');
    $this->abortIf(!is_numeric($count) || (int) $count > 0, 'Activated avatar generations require compatible readers.');
    $this->addSql('ALTER TABLE users DROP avatar_storage_key');
  }
}
