<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261006100002
 *
 * Adds the main replacement operation journal without altering historical equipment identities.
 *
 * @category Migration
 */
final class Version20261006100002 extends AbstractMigration
{
  // #region Methods
  /**
   * Method getDescription
   *
   * @access public
   *
   * @return string the migration purpose
   */
  public function getDescription(): string
  {
    return 'Add organization-scoped equipment replacement receipts.';
  }

  /**
   * Method up
   *
   * @access public
   *
   * @param Schema $schema the main schema
   *
   * @return void
   */
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE equipment_replacement_receipts (organization_id VARCHAR(36) NOT NULL, client_operation_id VARCHAR(36) NOT NULL, predecessor_equipment_id VARCHAR(36) NOT NULL, successor_equipment_id VARCHAR(36) NOT NULL, payload_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(organization_id, client_operation_id))');
    $this->addSql("COMMENT ON COLUMN equipment_replacement_receipts.created_at IS '(DC2Type:datetime_immutable)'");
  }

  /**
   * Method down
   *
   * @access public
   *
   * @param Schema $schema the main schema
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE equipment_replacement_receipts');
  }
  // #endregion
}
