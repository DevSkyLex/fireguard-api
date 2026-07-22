<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Fireguard Collaboration" integration plan — lots B2 (conversation links)
 * and B3 (structured message references).
 *
 * Creates `messaging_message_link` (URLs extracted from a message body on
 * create/edit — B2) and adds a nullable `references` JSON column to
 * `messaging_messages` (structured `{type, id, label?, code?}` rich-card
 * references attached to a message — B3).
 *
 * Every statement is strictly ADDITIVE — a new table and a nullable column —
 * safe to deploy on its own; no existing row is touched.
 *
 * Hand-written rather than generated: the `auth` and `main` entity managers
 * share one Postgres schema in development, so `doctrine:migrations:diff`
 * against `main` proposes dropping every `auth` table (`users`, `sessions`,
 * `audit_events`, `otps`, `clients`, `tenants`, …) — see `src/Messaging/MODULE.md`.
 * Only the statements below are intended.
 */
final class Version20260721090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messaging: messaging_message_link table (B2 conversation links) and messaging_messages.references JSON column (B3 structured message references).';
    }

    public function up(Schema $schema): void
    {
        // --- B2: conversation links -------------------------------------------------
        // No FK on message_id: a hard DELETE of a message never happens today
        // (only tombstoning, see MODULE.md), but the ON DELETE CASCADE FK still
        // guards the theoretical case (e.g. a threaded-reply subtree cascade)
        // exactly like messaging_attachments/messaging_reactions do.
        $this->addSql('CREATE TABLE messaging_message_link (id VARCHAR(36) NOT NULL, message_id VARCHAR(36) NOT NULL, conversation_id VARCHAR(36) NOT NULL, url VARCHAR(2048) NOT NULL, label VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_messaging_message_link_conversation ON messaging_message_link (conversation_id, created_at)');
        $this->addSql('CREATE INDEX idx_messaging_message_link_message ON messaging_message_link (message_id)');
        $this->addSql('COMMENT ON COLUMN messaging_message_link.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE messaging_message_link ADD CONSTRAINT fk_messaging_message_link_message FOREIGN KEY (message_id) REFERENCES messaging_messages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- B3: structured message references ---------------------------------------
        // Nullable: every existing message has no references, and a message may
        // never carry any (references are entirely optional rich cards).
        $this->addSql('ALTER TABLE messaging_messages ADD "references" JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messaging_messages DROP "references"');

        $this->addSql('ALTER TABLE messaging_message_link DROP CONSTRAINT fk_messaging_message_link_message');
        $this->addSql('DROP TABLE messaging_message_link');
    }
}
