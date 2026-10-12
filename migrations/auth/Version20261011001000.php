<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261011001000
 *
 * Stores durable HTTP result receipts solely in the owning auth database.
 *
 * @category Migration
 */
final class Version20261011001000 extends AbstractMigration
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
    return 'Add durable scoped HTTP idempotency reservations and result tombstones';
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
    $this->addSql("CREATE TABLE http_idempotency_receipts (
      scope_hash VARCHAR(64) NOT NULL,
      key_hash VARCHAR(64) NOT NULL,
      fingerprint VARCHAR(64) NOT NULL,
      owner_token VARCHAR(64) NOT NULL,
      principal_hash VARCHAR(64) NOT NULL,
      organization_hash VARCHAR(64) DEFAULT NULL,
      state VARCHAR(24) NOT NULL,
      lease_expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
      created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
      completed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
      result_expires_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
      result_payload TEXT DEFAULT NULL,
      proof_payload TEXT DEFAULT NULL,
      proof_mac VARCHAR(64) DEFAULT NULL,
      secret_result BOOLEAN NOT NULL DEFAULT FALSE,
      PRIMARY KEY (scope_hash, key_hash),
      CONSTRAINT http_idempotency_state CHECK (state IN ('pending', 'writing', 'completed', 'outcome_unknown')),
      CONSTRAINT http_idempotency_completion CHECK (
        (state = 'completed' AND completed_at IS NOT NULL AND result_expires_at IS NOT NULL AND proof_payload IS NOT NULL AND proof_mac IS NOT NULL)
        OR (state <> 'completed' AND completed_at IS NULL AND result_expires_at IS NULL AND result_payload IS NULL AND proof_payload IS NULL AND proof_mac IS NULL)
      )
    )");
    $this->addSql("CREATE INDEX http_idempotency_expired_results ON http_idempotency_receipts (result_expires_at) WHERE state = 'completed' AND result_payload IS NOT NULL");
    $this->addSql('CREATE INDEX http_idempotency_principal ON http_idempotency_receipts (principal_hash)');
    $this->addSql('CREATE INDEX http_idempotency_organization ON http_idempotency_receipts (organization_hash) WHERE organization_hash IS NOT NULL');
  }

  /**
   * Method down
   *
   * Removes replay data and its deduplication history; do not roll back while clients can retry.
   *
   * @access public
   *
   * @param Schema $schema current auth schema
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE http_idempotency_receipts');
  }
  // #endregion
}
