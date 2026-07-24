<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Wave-2 schema lot (L2.0): inert infrastructure ahead of the wave's feature
 * lots.
 *
 * Adds threaded-reply columns to `messaging_messages` (`parent_message_id`,
 * `reply_count` — activated by L2.5) and a channel-hierarchy column to
 * `messaging_conversations` (`parent_conversation_id` — activated by L2.6),
 * then creates the tables backing the two brand-new modules scaffolded
 * alongside this lot: `assistant_threads`/`assistant_messages` (Assistant —
 * AI chat, activated starting L2.1) and `calendar_events` (Calendar — the
 * shared organization calendar, activated by a later lot).
 *
 * Every statement is strictly ADDITIVE — new tables, nullable columns, new
 * indexes — because this lot lands inert ahead of the feature lots that
 * consume it, and must be safe to deploy on its own. There is deliberately
 * NO presence table: online presence uses `CachePort` with a TTL, not
 * persistent storage.
 *
 * Hand-written rather than generated: the `auth` and `main` entity managers
 * share one Postgres schema in development, so `doctrine:migrations:diff`
 * against `main` proposes dropping every `auth` table (`users`, `sessions`,
 * `audit_events`, `otps`, `clients`, `tenants`, …). Only the statements below
 * are intended.
 */
final class Version20260718124213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wave-2 schema lot (L2.0): messaging threaded replies + channel hierarchy, assistant_threads/assistant_messages, calendar_events.';
    }

    public function up(Schema $schema): void
    {
        // --- Messaging: threaded replies (scaffolded here, activated by L2.5) -----
        $this->addSql('ALTER TABLE messaging_messages ADD parent_message_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE messaging_messages ADD reply_count INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_messaging_message_parent ON messaging_messages (parent_message_id)');
        $this->addSql('ALTER TABLE messaging_messages ADD CONSTRAINT fk_messaging_message_parent FOREIGN KEY (parent_message_id) REFERENCES messaging_messages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- Messaging: channel hierarchy (scaffolded here, activated by L2.6) ----
        // ON DELETE SET NULL, not CASCADE: deleting a parent channel must not
        // silently delete its children — they detach and become top-level.
        $this->addSql('ALTER TABLE messaging_conversations ADD parent_conversation_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_messaging_conversation_parent ON messaging_conversations (parent_conversation_id)');
        $this->addSql('ALTER TABLE messaging_conversations ADD CONSTRAINT fk_messaging_conversation_parent FOREIGN KEY (parent_conversation_id) REFERENCES messaging_conversations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- Assistant module (new) ------------------------------------------------
        // No foreign key to `organizations`: mirrors `automation_runs` /
        // `approval_requests` — Assistant never depends on the Organization ORM
        // mapping.
        $this->addSql('CREATE TABLE assistant_threads (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, title VARCHAR(255) DEFAULT NULL, model VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_message_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_assistant_thread_org_member_last_message ON assistant_threads (organization_id, member_id, last_message_at)');
        $this->addSql('COMMENT ON COLUMN assistant_threads.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN assistant_threads.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN assistant_threads.last_message_at IS \'(DC2Type:datetime_immutable)\'');

        // `status` is load-bearing: it is what lets a Messenger retry REPLACE a
        // partially-streamed reply in place instead of appending a second one.
        $this->addSql('CREATE TABLE assistant_messages (id VARCHAR(36) NOT NULL, thread_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, role VARCHAR(16) NOT NULL, body TEXT NOT NULL, status VARCHAR(16) NOT NULL, error_code VARCHAR(64) DEFAULT NULL, token_count INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_assistant_message_thread_created ON assistant_messages (thread_id, created_at)');
        $this->addSql('COMMENT ON COLUMN assistant_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN assistant_messages.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE assistant_messages ADD CONSTRAINT fk_assistant_message_thread FOREIGN KEY (thread_id) REFERENCES assistant_threads (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- Calendar module (new) -------------------------------------------------
        // No foreign key to `organizations`/`facilities`: same rationale as
        // `assistant_threads` above.
        $this->addSql('CREATE TABLE calendar_events (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, all_day BOOLEAN DEFAULT false NOT NULL, facility_id VARCHAR(36) DEFAULT NULL, created_by_member_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_calendar_event_org_starts ON calendar_events (organization_id, starts_at)');
        $this->addSql('COMMENT ON COLUMN calendar_events.starts_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN calendar_events.ends_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN calendar_events.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN calendar_events.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE calendar_events');

        $this->addSql('ALTER TABLE assistant_messages DROP CONSTRAINT fk_assistant_message_thread');
        $this->addSql('DROP TABLE assistant_messages');
        $this->addSql('DROP TABLE assistant_threads');

        $this->addSql('ALTER TABLE messaging_conversations DROP CONSTRAINT fk_messaging_conversation_parent');
        $this->addSql('DROP INDEX idx_messaging_conversation_parent');
        $this->addSql('ALTER TABLE messaging_conversations DROP parent_conversation_id');

        $this->addSql('ALTER TABLE messaging_messages DROP CONSTRAINT fk_messaging_message_parent');
        $this->addSql('DROP INDEX idx_messaging_message_parent');
        $this->addSql('ALTER TABLE messaging_messages DROP reply_count');
        $this->addSql('ALTER TABLE messaging_messages DROP parent_message_id');
    }
}
