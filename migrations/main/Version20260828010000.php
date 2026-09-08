<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260828010000.
 *
 * Creates `member_calendar_feed_tokens` — the member-scoped iCal
 * subscription tokens of the Calendar module (phase 10a). Stores only the
 * SHA-256 hash of the secret (`token_hash`, unique); the raw secret is
 * shown once at creation and never persisted. `organization_id`/`user_id`
 * are plain identifier columns (the User record lives on the auth
 * database — no key may cross that line), mirroring `calendar_events`.
 * The auto-generated cross-database noise (a DROP of every auth-side table
 * visible to the diff) was hand-trimmed, as in every main migration.
 */
final class Version20260828010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the member_calendar_feed_tokens table for member iCal feed subscription tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE member_calendar_feed_tokens (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_member_calendar_feed_token_org_user ON member_calendar_feed_tokens (organization_id, user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_member_calendar_feed_token_hash ON member_calendar_feed_tokens (token_hash)');
        $this->addSql('COMMENT ON COLUMN member_calendar_feed_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN member_calendar_feed_tokens.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN member_calendar_feed_tokens.revoked_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member_calendar_feed_tokens');
    }
}
