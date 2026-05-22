<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417103000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add auth token and session lookup indexes';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_session_access_token_id ON sessions (access_token_id)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_session_refresh_token_id ON sessions (refresh_token_id)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_access_token_expiry ON access_tokens (expiry)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_refresh_token_expiry ON refresh_tokens (expiry)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_auth_code_expiry ON auth_codes (expiry)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_auth_code_expiry');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_refresh_token_expiry');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_access_token_expiry');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_session_refresh_token_id');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_session_access_token_id');
    }
}
