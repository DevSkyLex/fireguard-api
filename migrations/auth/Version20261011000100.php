<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261011000100
 *
 * Stores remaining MFA recovery digests and their generation time on the owning enrollment.
 *
 * @category Migration
 */
final class Version20261011000100 extends AbstractMigration
{
  // #region Methods
  /**
   * Method getDescription
   *
   * @access public
   *
   * @return string migration purpose
   */
  public function getDescription(): string
  {
    return 'Add one-time TOTP recovery code hashes and generation timestamp';
  }

  /**
   * Method up
   *
   * @access public
   *
   * @param Schema $schema current auth schema
   *
   * @return void
   */
  public function up(Schema $schema): void
  {
    $this->addSql("ALTER TABLE totp_enrollments ADD recovery_code_hashes JSON NOT NULL DEFAULT '[]'");
    $this->addSql('ALTER TABLE totp_enrollments ADD recovery_codes_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql("COMMENT ON COLUMN totp_enrollments.recovery_codes_generated_at IS '(DC2Type:datetime_immutable)'");
  }

  /**
   * Method down
   *
   * Removes recovery credentials; previously generated recovery codes will no longer be usable.
   *
   * @access public
   *
   * @param Schema $schema current auth schema
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE totp_enrollments DROP recovery_code_hashes');
    $this->addSql('ALTER TABLE totp_enrollments DROP recovery_codes_generated_at');
  }
  // #endregion
}
