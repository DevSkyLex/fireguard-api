<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260614090000.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260614090000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * Executes the get description operation.
   *
   * @since 1.0.0
   *
   * @return string the get description result
   */
  public function getDescription(): string
  {
    return 'Add revision-based conditional deletion to canonical media';
  }

  /**
   * Method up.
   *
   * Executes the up operation.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE equipment_attachments ADD revision INT DEFAULT 1 NOT NULL');
  }

  /**
   * Method down.
   *
   * Executes the down operation.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE equipment_attachments DROP revision');
  }
}
