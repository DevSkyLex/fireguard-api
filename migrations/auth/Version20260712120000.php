<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260712120000.
 *
 * Creates the "totp_enrollments" table, holding a user's TOTP (authenticator
 * app) enrollment: an optional active (confirmed) secret and an optional
 * pending (unconfirmed) secret, with confirmation attempt bookkeeping.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260712120000 extends AbstractMigration
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
    return 'Create totp_enrollments table for TOTP MFA enrollment (setup/confirm/disable)';
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
    $this->addSql(<<<'SQL'
      CREATE TABLE totp_enrollments (
        user_id VARCHAR(36) NOT NULL,
        active_secret VARCHAR(255) DEFAULT NULL,
        active_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
        pending_secret VARCHAR(255) DEFAULT NULL,
        pending_created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
        attempts INT NOT NULL,
        max_attempts INT NOT NULL,
        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        PRIMARY KEY(user_id)
      )
      SQL);
    $this->addSql('COMMENT ON COLUMN totp_enrollments.active_confirmed_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN totp_enrollments.pending_created_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN totp_enrollments.created_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN totp_enrollments.updated_at IS \'(DC2Type:datetime_immutable)\'');
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
    $this->addSql('DROP TABLE totp_enrollments');
  }
}
