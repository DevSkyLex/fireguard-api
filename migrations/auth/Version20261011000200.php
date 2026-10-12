<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261011000200
 *
 * Adds OIDC nonce proof and the issuer anchoring generic external identities.
 * Legacy provider flows and identities keep their existing nullable representation.
 *
 * @category Migration
 */
final class Version20261011000200 extends AbstractMigration
{
  // #region Methods
  /**
   * Method getDescription
   *
   * @access public
   *
   * @return string additive federation schema change
   */
  public function getDescription(): string
  {
    return 'Add hashed OIDC flow nonce and identity issuer';
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
    $this->addSql('ALTER TABLE federated_auth_flows ADD nonce_hash VARCHAR(64) DEFAULT NULL');
    $this->addSql('ALTER TABLE federated_identities ADD issuer VARCHAR(500) DEFAULT NULL');
  }

  /**
   * Method down
   *
   * @access public
   *
   * @param Schema $schema current auth schema
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE federated_identities DROP issuer');
    $this->addSql('ALTER TABLE federated_auth_flows DROP nonce_hash');
  }
  // #endregion
}
