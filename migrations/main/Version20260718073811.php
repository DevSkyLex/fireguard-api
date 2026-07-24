<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates `approval_requests` (R17 — configurable four-eyes approval
 * workflows). Uses a plain `organization_id` column (no ORM association, no
 * foreign key), mirroring `automation_runs`'s precedent: the Approval module
 * never depends on the Organization ORM mapping. The partial unique index
 * enforces one open (pending) request per `(organization, action type,
 * subject)` tuple — hand-written raw SQL since Doctrine's ORM attributes
 * cannot express a partial index.
 */
final class Version20260718073811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create approval_requests (R17 — configurable four-eyes approval workflows).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE approval_requests (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, action_type VARCHAR(64) NOT NULL, subject_id VARCHAR(36) NOT NULL, status VARCHAR(16) NOT NULL, requested_by_member_id VARCHAR(36) NOT NULL, requested_by_user_id VARCHAR(36) NOT NULL, decision_by_member_id VARCHAR(36) DEFAULT NULL, decision_by_user_id VARCHAR(36) DEFAULT NULL, decision_note TEXT DEFAULT NULL, payload JSON NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, executed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, execution_error TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_approval_request_org_status ON approval_requests (organization_id, status)');
        $this->addSql('CREATE INDEX idx_approval_request_org_action_subject ON approval_requests (organization_id, action_type, subject_id)');
        $this->addSql('CREATE INDEX idx_approval_request_status_expires ON approval_requests (status, expires_at)');
        // Partial unique index: at most one PENDING request per (organization, action type, subject) tuple.
        // Doctrine's ORM attributes cannot express a partial index, hence the raw SQL.
        $this->addSql('CREATE UNIQUE INDEX uniq_approval_request_org_action_subject_pending ON approval_requests (organization_id, action_type, subject_id) WHERE (status = \'pending\')');
        $this->addSql('COMMENT ON COLUMN approval_requests.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN approval_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN approval_requests.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN approval_requests.decided_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN approval_requests.executed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE approval_requests');
    }
}
