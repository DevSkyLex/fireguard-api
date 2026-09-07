<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260907140000.
 *
 * @category Migration
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260907140000 extends AbstractMigration
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @return string the migration intent
   */
  public function getDescription(): string
  {
    return 'Add explicit mailbox ownership provenance; legacy verified accounts remain unproven.';
  }

  /**
   * @since 1.0.0
   *
   * @param Schema $schema current schema
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE users ADD email_ownership_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql("COMMENT ON COLUMN users.email_ownership_verified_at IS '(DC2Type:datetime_immutable)'");
  }

  /**
   * @since 1.0.0
   *
   * @param Schema $schema current schema
   */
  public function down(Schema $schema): void
  {
    // Rollback removes recorded proof only; the original verification flag is preserved.
    $this->addSql('ALTER TABLE users DROP COLUMN email_ownership_verified_at');
  }
  // #endregion
}
