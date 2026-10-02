<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261002120000
 *
 * Accommodates opaque OAuth token identifiers without storing bearer credentials.
 *
 * @category Migration
 */
final class Version20261002120000 extends AbstractMigration
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
    return 'Allow the audit ledger to retain complete OAuth token identifiers';
  }

  /**
   * Method up
   *
   * @access public
   *
   * @param Schema $schema the current auth schema
   *
   * @return void
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE audit_events ALTER subject_id TYPE VARCHAR(100)');
  }

  /**
   * Method down
   *
   * Refuses to shorten existing hash-covered identifiers.
   *
   * @access public
   *
   * @param Schema $schema the current auth schema
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM audit_events WHERE CHAR_LENGTH(subject_id) > 64'), 'Audit identifiers longer than 64 characters must be retained.');
    $this->addSql('ALTER TABLE audit_events ALTER subject_id TYPE VARCHAR(64)');
  }
  // #endregion
}
