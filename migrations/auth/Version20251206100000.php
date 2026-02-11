<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Consent table.
 */
final class Version20251206100000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Create consents table for OAuth2 authorization code flow';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE consents (
            id UUID NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            client_id VARCHAR(36) NOT NULL,
            scopes JSON NOT NULL,
            granted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
    $this->addSql('CREATE UNIQUE INDEX uniq_user_client ON consents (user_id, client_id)');
    $this->addSql('CREATE INDEX idx_consent_user ON consents (user_id)');
    $this->addSql('COMMENT ON COLUMN consents.granted_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN consents.revoked_at IS \'(DC2Type:datetime_immutable)\'');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE consents');
  }
}
