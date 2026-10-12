<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds resumable verified-provider proof while retaining fail-closed remote exchange uncertainty. */
final class Version20261011000400 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add federated reserved, provider_verified and completed lifecycle with encrypted short-lived proof';
  }

  public function up(Schema $schema): void
  {
    $this->addSql("ALTER TABLE federated_auth_flows ADD stage VARCHAR(24) DEFAULT 'reserved' NOT NULL");
    $this->addSql('ALTER TABLE federated_auth_flows ADD exchange_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql('ALTER TABLE federated_auth_flows ADD verified_profile TEXT DEFAULT NULL');
    $this->addSql('ALTER TABLE federated_auth_flows ADD proof_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql('ALTER TABLE federated_auth_flows ADD completed_user_id VARCHAR(36) DEFAULT NULL');
    $this->addSql("ALTER TABLE federated_auth_flows ADD CONSTRAINT chk_federated_flow_stage CHECK (stage IN ('reserved', 'provider_verified', 'completed'))");
  }

  public function down(Schema $schema): void
  {
    $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM federated_auth_flows WHERE stage <> 'reserved' OR exchange_started_at IS NOT NULL") > 0, 'Refusing to discard active verified-provider or uncertain-exchange proof.');
    $this->addSql('ALTER TABLE federated_auth_flows DROP CONSTRAINT chk_federated_flow_stage');
    $this->addSql('ALTER TABLE federated_auth_flows DROP stage, DROP exchange_started_at, DROP verified_profile, DROP proof_expires_at, DROP completed_user_id');
  }
}
