<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds external identity links and one-time authorization-code flows.
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260906120000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add federated identities and PKCE flows; allow passwordless federated users';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE users ALTER password DROP NOT NULL');
    $this->addSql('CREATE TABLE federated_identities (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, provider VARCHAR(20) NOT NULL, subject VARCHAR(255) NOT NULL, email VARCHAR(320) NOT NULL, connected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_federated_provider_subject ON federated_identities (provider, subject)');
    $this->addSql('CREATE UNIQUE INDEX uniq_federated_user_provider ON federated_identities (user_id, provider)');
    $this->addSql('ALTER TABLE federated_identities ADD CONSTRAINT fk_federated_identity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql("COMMENT ON COLUMN federated_identities.connected_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN federated_identities.last_used_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql('CREATE TABLE federated_auth_flows (state_hash VARCHAR(64) NOT NULL, provider VARCHAR(20) NOT NULL, intent VARCHAR(10) NOT NULL, user_id VARCHAR(36) DEFAULT NULL, code_verifier TEXT NOT NULL, redirect_uri VARCHAR(500) NOT NULL, return_url VARCHAR(500) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(state_hash))');
    $this->addSql('CREATE INDEX idx_federated_flow_expiry ON federated_auth_flows (expires_at)');
    $this->addSql('ALTER TABLE federated_auth_flows ADD CONSTRAINT fk_federated_flow_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql("COMMENT ON COLUMN federated_auth_flows.expires_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN federated_auth_flows.consumed_at IS '(DC2Type:datetime_immutable)'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE federated_auth_flows');
    $this->addSql('DROP TABLE federated_identities');
    $this->addSql("UPDATE users SET password = '' WHERE password IS NULL");
    $this->addSql('ALTER TABLE users ALTER password SET NOT NULL');
  }
}
