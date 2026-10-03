<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20261003100000.
 *
 * @category Migration
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20261003100000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * @since 1.0.0
   */
  public function getDescription(): string
  {
    return 'Add optional floor dimensions and metric calibration of floor plan images.';
  }

  /**
   * Method up.
   *
   * @since 1.0.0
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE facilities ADD elevation_meters DOUBLE PRECISION DEFAULT NULL, ADD height_meters DOUBLE PRECISION DEFAULT NULL');
    $this->addSql('ALTER TABLE facility_attachments ADD calibration JSONB DEFAULT NULL');
  }

  /**
   * Method down.
   *
   * @since 1.0.0
   */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE facility_attachments DROP calibration');
    $this->addSql('ALTER TABLE facilities DROP elevation_meters, DROP height_meters');
  }
}
