<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261008121000
 *
 * Aligns retained supplier references with the public eighty-character domain limit.
 *
 * @category Migration
 */
final class Version20261008121000 extends AbstractMigration
{
  // #region Methods
  /**
   * Method getDescription
   *
   * Describes the additive supplier-reference expansion.
   *
   * @access public
   *
   * @return string the migration purpose
   */
  public function getDescription(): string
  {
    return 'Widen procurement supplier codes to the validated eighty-character limit.';
  }

  /**
   * Method up
   *
   * Expands the main supplier reference without changing retained values.
   *
   * @access public
   *
   * @param Schema $schema the main schema
   *
   * @return void
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE procurement_suppliers ALTER code TYPE VARCHAR(80)');
  }

  /**
   * Method down
   *
   * Restores the former bound only when every retained value still fits it.
   *
   * @access public
   *
   * @param Schema $schema the main schema
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    // PostgreSQL refuses rollback while any retained reference exceeds the former limit.
    $this->addSql('ALTER TABLE procurement_suppliers ALTER code TYPE VARCHAR(64)');
  }
  // #endregion
}
