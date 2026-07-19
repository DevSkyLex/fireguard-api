<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the Messaging module's three tables: `messaging_conversations`,
 * `messaging_messages`, `messaging_read_markers` (R6 — Messaging module v1).
 *
 * Hand-pruned from the raw `doctrine:migrations:diff` output, which also
 * picked up unrelated auth-database drift and pre-existing main-database
 * column/index churn — neither belongs in this migration.
 */
final class Version20260717102427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messaging module v1: messaging_conversations, messaging_messages, messaging_read_markers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE messaging_conversations (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, subject_type VARCHAR(32) NOT NULL, subject_id VARCHAR(36) DEFAULT NULL, visibility VARCHAR(16) NOT NULL, last_message_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, messages_count INT NOT NULL, is_archived BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6489D332C8A3DE ON messaging_conversations (organization_id)');
        $this->addSql('CREATE INDEX idx_messaging_conversation_org_archived_last_message ON messaging_conversations (organization_id, is_archived, last_message_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_messaging_conversation_org_subject ON messaging_conversations (organization_id, subject_type, subject_id)');
        $this->addSql('COMMENT ON COLUMN messaging_conversations.last_message_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messaging_conversations.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messaging_conversations.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE messaging_messages (id VARCHAR(36) NOT NULL, conversation_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, author_member_id VARCHAR(36) NOT NULL, body TEXT NOT NULL, mentions JSON NOT NULL, edited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_by_member_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E044CDE59AC0396 ON messaging_messages (conversation_id)');
        $this->addSql('CREATE INDEX idx_messaging_message_conversation_created ON messaging_messages (conversation_id, created_at)');
        $this->addSql('CREATE INDEX idx_messaging_message_organization ON messaging_messages (organization_id)');
        $this->addSql('COMMENT ON COLUMN messaging_messages.edited_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messaging_messages.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messaging_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messaging_messages.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE messaging_read_markers (member_id VARCHAR(36) NOT NULL, conversation_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, last_read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_read_message_id VARCHAR(36) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(conversation_id, member_id))');
        $this->addSql('CREATE INDEX IDX_1C0338009AC0396 ON messaging_read_markers (conversation_id)');
        $this->addSql('CREATE INDEX idx_messaging_read_marker_org_member ON messaging_read_markers (organization_id, member_id)');
        $this->addSql('COMMENT ON COLUMN messaging_read_markers.last_read_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messaging_read_markers.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE messaging_conversations ADD CONSTRAINT FK_6489D332C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE messaging_messages ADD CONSTRAINT FK_E044CDE59AC0396 FOREIGN KEY (conversation_id) REFERENCES messaging_conversations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE messaging_read_markers ADD CONSTRAINT FK_1C0338009AC0396 FOREIGN KEY (conversation_id) REFERENCES messaging_conversations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messaging_read_markers DROP CONSTRAINT FK_1C0338009AC0396');
        $this->addSql('ALTER TABLE messaging_messages DROP CONSTRAINT FK_E044CDE59AC0396');
        $this->addSql('ALTER TABLE messaging_conversations DROP CONSTRAINT FK_6489D332C8A3DE');

        $this->addSql('DROP TABLE messaging_read_markers');
        $this->addSql('DROP TABLE messaging_messages');
        $this->addSql('DROP TABLE messaging_conversations');
    }
}
