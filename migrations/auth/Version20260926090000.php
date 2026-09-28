<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260926090000.
 *
 * @category Migration
 * @version 1.0.0
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260926090000 extends AbstractMigration
{
  /** @since 1.0.0 */
  public function getDescription(): string { return 'Persist global user presence preferences on the auth database'; }
  /** @since 1.0.0 */
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE user_presence_preferences (user_id VARCHAR(36) NOT NULL, do_not_disturb BOOLEAN DEFAULT false NOT NULL, revision INT DEFAULT 0 NOT NULL, PRIMARY KEY (user_id))');
  }
  /** @since 1.0.0 */
  public function down(Schema $schema): void { $this->addSql('DROP TABLE user_presence_preferences'); }
}
