<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the user_email_change_requests table (auth database):
 * pending sign-in email change requests, storing only the SHA-256
 * hash of the confirmation token.
 */
final class Version20260828090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_email_change_requests (email change flow, token hash only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_email_change_requests (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, current_email VARCHAR(320) NOT NULL, new_email VARCHAR(320) NOT NULL, token_hash VARCHAR(64) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_user_email_change_request_user ON user_email_change_requests (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email_change_request_token_hash ON user_email_change_requests (token_hash)');
        $this->addSql('COMMENT ON COLUMN user_email_change_requests.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_email_change_requests.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_email_change_requests.confirmed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_email_change_requests');
    }
}
