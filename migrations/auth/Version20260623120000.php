<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260623120000.
 *
 * Adds the "locale" column to the users table, storing the user's preferred
 * display language. The default value "system" means the user follows their
 * browser language.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260623120000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the get description result
   */
  public function getDescription(): string
  {
    return 'Add locale column to users for the preferred display language';
  }

  /**
   * Method up.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->addSql("ALTER TABLE users ADD locale VARCHAR(10) NOT NULL DEFAULT 'system'");
  }

  /**
   * Method down.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE users DROP locale');
  }
}
