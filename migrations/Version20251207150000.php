<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for TrustedDevice table.
 */
final class Version20251207150000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Create trusted_devices table for 2FA bypass';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE trusted_devices (
            id UUID NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            fingerprint VARCHAR(255) NOT NULL,
            user_agent VARCHAR(500) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            revoked BOOLEAN DEFAULT false NOT NULL,
            PRIMARY KEY(id)
        )');
    $this->addSql('CREATE INDEX idx_td_user ON trusted_devices (user_id)');
    $this->addSql('CREATE INDEX idx_td_token ON trusted_devices (token_hash)');
    $this->addSql('CREATE UNIQUE INDEX uniq_td_user_fingerprint ON trusted_devices (user_id, fingerprint)');
    $this->addSql('COMMENT ON COLUMN trusted_devices.last_used_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN trusted_devices.expires_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN trusted_devices.created_at IS \'(DC2Type:datetime_immutable)\'');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE trusted_devices');
  }
}
