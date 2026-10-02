<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20260930201001
 *
 * Adds optional registered-office and privacy-contact details to organizations
 * in the main database without changing existing profiles.
 *
 * @category Migration
 */
final class Version20260930201001 extends AbstractMigration
{
  // #region Methods
  /**
   * Method getDescription
   *
   * @access public
   *
   * @return string the organization profile schema change
   */
  public function getDescription(): string
  {
    return 'Add optional registered address and privacy contact email to organizations in main';
  }

  /**
   * Method up
   *
   * Keeps existing organizations valid with both new columns initially null.
   *
   * @access public
   *
   * @param Schema $schema the migration schema
   *
   * @return void
   */
  public function up(Schema $schema): void
  {
    $this->abortIf(
      'postgresql' !== $this->connection->getDatabasePlatform()->getName(),
      'This migration is intended for PostgreSQL only.',
    );

    $this->addSql('ALTER TABLE organizations ADD registered_address JSONB DEFAULT NULL');
    $this->addSql('ALTER TABLE organizations ADD privacy_contact_email VARCHAR(254) DEFAULT NULL');
  }

  /**
   * Method down
   *
   * Removes only the new columns and discards any values stored in them.
   *
   * @access public
   *
   * @param Schema $schema the migration schema
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->abortIf(
      'postgresql' !== $this->connection->getDatabasePlatform()->getName(),
      'This migration is intended for PostgreSQL only.',
    );

    $this->addSql('ALTER TABLE organizations DROP registered_address');
    $this->addSql('ALTER TABLE organizations DROP privacy_contact_email');
  }
  // #endregion
}
