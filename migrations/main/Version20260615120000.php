<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260615120000.
 *
 * Drops the intervention reference_pack_id column: the reference-pack concept
 * (regulatory regime catalog) drove no behaviour and is removed.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260615120000 extends AbstractMigration
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
    return 'Drop interventions.reference_pack_id (reference pack concept removed)';
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
    $this->addSql('ALTER TABLE interventions DROP COLUMN reference_pack_id');
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
    $this->addSql("ALTER TABLE interventions ADD COLUMN reference_pack_id VARCHAR(80) NOT NULL DEFAULT 'fr-erp-ert-v1'");
    $this->addSql('ALTER TABLE interventions ALTER COLUMN reference_pack_id DROP DEFAULT');
  }
}
