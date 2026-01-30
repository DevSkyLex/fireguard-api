<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130092608 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_event_chains (chain_id VARCHAR(128) NOT NULL, last_hash VARCHAR(64) NOT NULL, last_sequence BIGINT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(chain_id))');
        $this->addSql('COMMENT ON COLUMN audit_event_chains.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE audit_events (id UUID NOT NULL, chain_id VARCHAR(128) NOT NULL, sequence BIGINT NOT NULL, action VARCHAR(120) NOT NULL, actor_type VARCHAR(32) NOT NULL, actor_id VARCHAR(64) DEFAULT NULL, actor_email VARCHAR(255) DEFAULT NULL, actor_email_hash VARCHAR(64) DEFAULT NULL, subject_type VARCHAR(32) DEFAULT NULL, subject_id VARCHAR(64) DEFAULT NULL, client_id VARCHAR(64) DEFAULT NULL, tenant_id VARCHAR(64) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, ip_hash VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, metadata JSON NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, prev_hash VARCHAR(64) DEFAULT NULL, event_hash VARCHAR(64) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_audit_occurred_at ON audit_events (occurred_at)');
        $this->addSql('CREATE INDEX idx_audit_action ON audit_events (action)');
        $this->addSql('CREATE INDEX idx_audit_actor_id ON audit_events (actor_id)');
        $this->addSql('CREATE INDEX idx_audit_actor_email_hash ON audit_events (actor_email_hash)');
        $this->addSql('CREATE INDEX idx_audit_subject_id ON audit_events (subject_id)');
        $this->addSql('CREATE INDEX idx_audit_client_id ON audit_events (client_id)');
        $this->addSql('CREATE INDEX idx_audit_tenant_id ON audit_events (tenant_id)');
        $this->addSql('CREATE INDEX idx_audit_ip_hash ON audit_events (ip_hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_audit_chain_sequence ON audit_events (chain_id, sequence)');
        $this->addSql('COMMENT ON COLUMN audit_events.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_events.occurred_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN audit_events.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE sessions (id UUID NOT NULL, user_id VARCHAR(36) NOT NULL, access_token_id VARCHAR(255) DEFAULT NULL, refresh_token_id VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(512) NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_session_user_id ON sessions (user_id)');
        $this->addSql('CREATE INDEX idx_session_user_active ON sessions (user_id, revoked_at)');
        $this->addSql('COMMENT ON COLUMN sessions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sessions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sessions.last_activity_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sessions.revoked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tenants (id UUID NOT NULL, name VARCHAR(100) NOT NULL, settings JSON NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8FC96BB5E237E06 ON tenants (name)');
        $this->addSql('COMMENT ON COLUMN tenants.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenants.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE trusted_devices (id UUID NOT NULL, user_id VARCHAR(36) NOT NULL, token_hash VARCHAR(255) NOT NULL, fingerprint VARCHAR(255) NOT NULL, user_agent VARCHAR(500) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, name VARCHAR(255) NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_td_user ON trusted_devices (user_id)');
        $this->addSql('CREATE INDEX idx_td_token ON trusted_devices (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_td_user_fingerprint ON trusted_devices (user_id, fingerprint)');
        $this->addSql('COMMENT ON COLUMN trusted_devices.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN trusted_devices.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN trusted_devices.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE audit_event_chains');
        $this->addSql('DROP TABLE audit_events');
        $this->addSql('DROP TABLE sessions');
        $this->addSql('DROP TABLE tenants');
        $this->addSql('DROP TABLE trusted_devices');
    }
}
