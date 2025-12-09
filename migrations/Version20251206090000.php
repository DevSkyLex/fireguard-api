<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Session and Tenant tables.
 */
final class Version20251206090000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Create sessions and tenants tables for SSO functionality';
  }

  public function up(Schema $schema): void
  {
    // Sessions table
    $this->addSql('CREATE TABLE sessions (
            id UUID NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            access_token_id VARCHAR(255) DEFAULT NULL,
            refresh_token_id VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(512) NOT NULL,
            metadata JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
    $this->addSql('CREATE INDEX idx_session_user_id ON sessions (user_id)');
    $this->addSql('CREATE INDEX idx_session_user_active ON sessions (user_id, revoked_at)');
    $this->addSql('COMMENT ON COLUMN sessions.created_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN sessions.last_activity_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN sessions.revoked_at IS \'(DC2Type:datetime_immutable)\'');

    // Tenants table
    $this->addSql('CREATE TABLE tenants (
            id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            settings JSON NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
    $this->addSql('CREATE UNIQUE INDEX uniq_tenant_name ON tenants (name)');
    $this->addSql('COMMENT ON COLUMN tenants.created_at IS \'(DC2Type:datetime_immutable)\'');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE sessions');
    $this->addSql('DROP TABLE tenants');
  }
}
