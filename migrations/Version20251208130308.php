<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208130308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE trusted_devices');
        $this->addSql('DROP TABLE otps');
        $this->addSql('DROP TABLE tenants');
        $this->addSql('DROP TABLE sessions');
        $this->addSql('ALTER TABLE consents ALTER id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN consents.id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE trusted_devices (id UUID NOT NULL, user_id VARCHAR(36) NOT NULL, token_hash VARCHAR(255) NOT NULL, fingerprint VARCHAR(255) NOT NULL, user_agent VARCHAR(500) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, name VARCHAR(255) NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_td_token ON trusted_devices (token_hash)');
        $this->addSql('CREATE INDEX idx_td_user ON trusted_devices (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_td_user_fingerprint ON trusted_devices (user_id, fingerprint)');
        $this->addSql('COMMENT ON COLUMN trusted_devices.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN trusted_devices.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN trusted_devices.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE otps (id UUID NOT NULL, user_id VARCHAR(36) NOT NULL, purpose VARCHAR(50) NOT NULL, channel VARCHAR(20) NOT NULL, code_hash VARCHAR(255) NOT NULL, recipient VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, attempts INT DEFAULT 0 NOT NULL, max_attempts INT NOT NULL, verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_otp_expires ON otps (expires_at)');
        $this->addSql('CREATE INDEX idx_otp_user_purpose ON otps (user_id, purpose)');
        $this->addSql('COMMENT ON COLUMN otps.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN otps.verified_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN otps.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tenants (id UUID NOT NULL, name VARCHAR(100) NOT NULL, settings JSON NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_name ON tenants (name)');
        $this->addSql('COMMENT ON COLUMN tenants.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE sessions (id UUID NOT NULL, user_id VARCHAR(36) NOT NULL, access_token_id VARCHAR(255) DEFAULT NULL, refresh_token_id VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(512) NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_session_user_active ON sessions (user_id, revoked_at)');
        $this->addSql('CREATE INDEX idx_session_user_id ON sessions (user_id)');
        $this->addSql('COMMENT ON COLUMN sessions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sessions.last_activity_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sessions.revoked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE consents ALTER id TYPE UUID');
        $this->addSql('COMMENT ON COLUMN consents.id IS NULL');
    }
}
