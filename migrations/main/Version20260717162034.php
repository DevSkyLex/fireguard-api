<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260717162034.
 *
 * R15 — Messaging v2: team channels + participants. Adds the
 * `messaging_participants` table (main DB): the explicit membership list
 * gating a `visibility=participants` channel, composite-keyed
 * (`conversation_id`, `member_id`), FK `conversation_id` -> ON DELETE
 * CASCADE. Also adds three nullable channel-only columns on the existing
 * `messaging_conversations` table: `name`, `team_id` (a plain cross-module
 * reference column, mirroring `subject_id` — no ORM association), and
 * `created_by_member_id`. A v1 subject-thread conversation leaves all three
 * null; the existing `uniq_messaging_conversation_org_subject` unique index
 * is untouched and already tolerates unlimited channel rows because
 * Postgres treats NULL `subject_id` as distinct across rows.
 *
 * Hand-pruned from the raw `doctrine:migrations:diff` output: only the
 * `messaging_participants` table/indexes/FK and the three additive
 * `messaging_conversations` columns are kept here — unrelated
 * cross-entity-manager drift (auth-database tables, pre-existing schema
 * differences from other in-flight lots) is intentionally dropped, mirroring
 * `Version20260717134458`'s precedent.
 */
final class Version20260717162034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R15: add messaging_participants table + channel columns (name/team_id/created_by_member_id) on messaging_conversations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messaging_conversations ADD name VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE messaging_conversations ADD team_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE messaging_conversations ADD created_by_member_id VARCHAR(36) DEFAULT NULL');

        $this->addSql('CREATE TABLE messaging_participants (conversation_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, role VARCHAR(32) DEFAULT NULL, source VARCHAR(16) NOT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(conversation_id, member_id))');
        $this->addSql('CREATE INDEX IDX_9004F52E9AC0396 ON messaging_participants (conversation_id)');
        $this->addSql('CREATE INDEX idx_messaging_participant_org_member ON messaging_participants (organization_id, member_id)');
        $this->addSql('COMMENT ON COLUMN messaging_participants.added_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE messaging_participants ADD CONSTRAINT FK_9004F52E9AC0396 FOREIGN KEY (conversation_id) REFERENCES messaging_conversations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messaging_participants DROP CONSTRAINT FK_9004F52E9AC0396');
        $this->addSql('DROP TABLE messaging_participants');

        $this->addSql('ALTER TABLE messaging_conversations DROP name');
        $this->addSql('ALTER TABLE messaging_conversations DROP team_id');
        $this->addSql('ALTER TABLE messaging_conversations DROP created_by_member_id');
    }
}
