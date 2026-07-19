<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Wave-1 schema lot for the Messaging v3 / notification-spine work package.
 *
 * Creates the four Messaging satellite tables (attachments, reactions, saved
 * messages, conversation favorites) plus `notification_preferences`, and adds
 * the columns the wave's feature lots activate: message pinning,
 * `notifications.organization_id`, and `checklists.reference_code`.
 *
 * Every statement is strictly ADDITIVE — new tables, nullable columns, new
 * indexes — because this lot lands inert ahead of the ten feature lots that
 * consume it, and must be safe to deploy on its own.
 *
 * Hand-written rather than generated: the `auth` and `main` entity managers
 * share one Postgres schema in development, so `doctrine:migrations:diff`
 * against `main` proposes dropping every `auth` table (`users`, `sessions`,
 * `audit_events`, …) along with unrelated pre-existing metadata drift. Only
 * the statements below are intended.
 */
final class Version20260718115756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wave-1 schema lot: messaging attachments/reactions/saves/favorites, notification preferences, message pinning, notification org scope, checklist reference codes.';
    }

    public function up(Schema $schema): void
    {
        // --- Messaging satellites -------------------------------------------------
        $this->addSql('CREATE TABLE messaging_attachments (id VARCHAR(36) NOT NULL, message_id VARCHAR(36) NOT NULL, conversation_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, uploaded_by_member_id VARCHAR(36) NOT NULL, file_name VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL, label VARCHAR(255) DEFAULT NULL, revision INT NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_messaging_attachment_message ON messaging_attachments (message_id)');
        $this->addSql('CREATE INDEX idx_messaging_attachment_conversation_uploaded ON messaging_attachments (conversation_id, uploaded_at)');
        $this->addSql('CREATE INDEX idx_messaging_attachment_organization ON messaging_attachments (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_messaging_attachment_storage_path ON messaging_attachments (storage_path)');
        $this->addSql('COMMENT ON COLUMN messaging_attachments.uploaded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE messaging_attachments ADD CONSTRAINT FK_24D95E9D537A1329 FOREIGN KEY (message_id) REFERENCES messaging_messages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // The emoji is part of the primary key: that is what makes "react"
        // idempotent and "un-react" a plain delete, with no read-modify-write.
        $this->addSql('CREATE TABLE messaging_reactions (message_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, emoji VARCHAR(32) NOT NULL, organization_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(message_id, member_id, emoji))');
        $this->addSql('CREATE INDEX idx_messaging_reaction_message ON messaging_reactions (message_id)');
        $this->addSql('CREATE INDEX idx_messaging_reaction_org_member ON messaging_reactions (organization_id, member_id)');
        $this->addSql('COMMENT ON COLUMN messaging_reactions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE messaging_reactions ADD CONSTRAINT FK_F14419E6537A1329 FOREIGN KEY (message_id) REFERENCES messaging_messages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE messaging_saved_messages (member_id VARCHAR(36) NOT NULL, message_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, saved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(member_id, message_id))');
        $this->addSql('CREATE INDEX IDX_60416EC8537A1329 ON messaging_saved_messages (message_id)');
        $this->addSql('CREATE INDEX idx_messaging_saved_org_member_saved ON messaging_saved_messages (organization_id, member_id, saved_at)');
        $this->addSql('COMMENT ON COLUMN messaging_saved_messages.saved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE messaging_saved_messages ADD CONSTRAINT FK_60416EC8537A1329 FOREIGN KEY (message_id) REFERENCES messaging_messages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE messaging_conversation_favorites (conversation_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, favorited_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(conversation_id, member_id))');
        $this->addSql('CREATE INDEX IDX_AF0F08509AC0396 ON messaging_conversation_favorites (conversation_id)');
        $this->addSql('CREATE INDEX idx_messaging_favorite_org_member ON messaging_conversation_favorites (organization_id, member_id)');
        $this->addSql('COMMENT ON COLUMN messaging_conversation_favorites.favorited_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE messaging_conversation_favorites ADD CONSTRAINT FK_AF0F08509AC0396 FOREIGN KEY (conversation_id) REFERENCES messaging_conversations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- Message pinning ------------------------------------------------------
        $this->addSql('ALTER TABLE messaging_messages ADD pinned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE messaging_messages ADD pinned_by_member_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN messaging_messages.pinned_at IS \'(DC2Type:datetime_immutable)\'');
        // Partial index: pins are a tiny minority of rows, and the Pins tab only
        // ever queries the pinned ones. Doctrine's ORM attributes cannot express
        // a partial index, hence the raw SQL.
        $this->addSql('CREATE INDEX idx_messaging_message_pinned ON messaging_messages (conversation_id, pinned_at) WHERE pinned_at IS NOT NULL');

        // --- Notification spine ---------------------------------------------------
        // Nullable: account-level notifications (`user.email_verified`) and
        // platform announcements legitimately have no organization, and every
        // pre-existing row has none.
        $this->addSql('ALTER TABLE notifications ADD organization_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_notifications_user_org_created ON notifications (recipient_user_id, organization_id, created_at)');

        // No foreign key on `user_id`: notifications live on `main` while `users`
        // lives on `auth`, so a cross-database constraint is not expressible —
        // the same reason `notifications.recipient_user_id` is a plain column.
        $this->addSql('CREATE TABLE notification_preferences (user_id VARCHAR(36) NOT NULL, category VARCHAR(64) NOT NULL, email_enabled BOOLEAN DEFAULT true NOT NULL, mercure_enabled BOOLEAN DEFAULT true NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(user_id, category))');
        $this->addSql('CREATE INDEX idx_notification_preference_user ON notification_preferences (user_id)');
        $this->addSql('COMMENT ON COLUMN notification_preferences.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // --- Checklist reference codes -------------------------------------------
        // Nullable + unique-per-organization: Postgres treats NULLs as distinct,
        // so every existing code-less checklist coexists under this constraint.
        $this->addSql('ALTER TABLE checklists ADD reference_code VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_checklist_organization_reference_code ON checklists (organization_id, reference_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_checklist_organization_reference_code');
        $this->addSql('ALTER TABLE checklists DROP reference_code');

        $this->addSql('DROP TABLE notification_preferences');
        $this->addSql('DROP INDEX idx_notifications_user_org_created');
        $this->addSql('ALTER TABLE notifications DROP organization_id');

        $this->addSql('DROP INDEX idx_messaging_message_pinned');
        $this->addSql('ALTER TABLE messaging_messages DROP pinned_by_member_id');
        $this->addSql('ALTER TABLE messaging_messages DROP pinned_at');

        $this->addSql('ALTER TABLE messaging_conversation_favorites DROP CONSTRAINT FK_AF0F08509AC0396');
        $this->addSql('ALTER TABLE messaging_saved_messages DROP CONSTRAINT FK_60416EC8537A1329');
        $this->addSql('ALTER TABLE messaging_reactions DROP CONSTRAINT FK_F14419E6537A1329');
        $this->addSql('ALTER TABLE messaging_attachments DROP CONSTRAINT FK_24D95E9D537A1329');
        $this->addSql('DROP TABLE messaging_conversation_favorites');
        $this->addSql('DROP TABLE messaging_saved_messages');
        $this->addSql('DROP TABLE messaging_reactions');
        $this->addSql('DROP TABLE messaging_attachments');
    }
}
