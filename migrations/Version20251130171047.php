<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251130171047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_tokens (identifier VARCHAR(100) NOT NULL, client_identifier VARCHAR(100) NOT NULL, user_identifier VARCHAR(100) DEFAULT NULL, scopes JSON NOT NULL, expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_revoked BOOLEAN NOT NULL, PRIMARY KEY(identifier))');
        $this->addSql('COMMENT ON COLUMN access_tokens.expiry IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE auth_codes (identifier VARCHAR(100) NOT NULL, client_identifier VARCHAR(100) NOT NULL, user_identifier VARCHAR(100) DEFAULT NULL, scopes JSON NOT NULL, redirect_uri TEXT DEFAULT NULL, expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_revoked BOOLEAN NOT NULL, PRIMARY KEY(identifier))');
        $this->addSql('COMMENT ON COLUMN auth_codes.expiry IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE refresh_tokens (identifier VARCHAR(100) NOT NULL, access_token_identifier VARCHAR(100) NOT NULL, client_identifier VARCHAR(100) NOT NULL, expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_revoked BOOLEAN NOT NULL, PRIMARY KEY(identifier))');
        $this->addSql('COMMENT ON COLUMN refresh_tokens.expiry IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, avatar_url VARCHAR(255) DEFAULT NULL, status VARCHAR(30) NOT NULL, email_verified BOOLEAN NOT NULL, tenant_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failed_login_attempts INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE INDEX idx_username ON users (username)');
        $this->addSql('CREATE INDEX idx_email ON users (email)');
        $this->addSql('CREATE INDEX idx_tenant_id ON users (tenant_id)');
        $this->addSql('CREATE INDEX idx_status ON users (status)');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.last_login_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE access_tokens');
        $this->addSql('DROP TABLE auth_codes');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE users');
    }
}
