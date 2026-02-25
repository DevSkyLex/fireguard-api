<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organization onboarding sessions table with state and rollback stack';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_onboarding_sessions (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, flow VARCHAR(32) NOT NULL, state VARCHAR(20) NOT NULL, next_step VARCHAR(64) DEFAULT NULL, blocked_reason VARCHAR(120) DEFAULT NULL, target_organization_id VARCHAR(36) DEFAULT NULL, target_organization_name VARCHAR(120) DEFAULT NULL, completed_steps JSON NOT NULL, rollback_stack JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_onboarding_org_session_user ON organization_onboarding_sessions (user_id)');
        $this->addSql('CREATE INDEX idx_onboarding_org_session_state ON organization_onboarding_sessions (state)');
        $this->addSql('CREATE INDEX idx_onboarding_org_session_target_org ON organization_onboarding_sessions (target_organization_id)');
        $this->addSql("COMMENT ON COLUMN organization_onboarding_sessions.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN organization_onboarding_sessions.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE organization_onboarding_sessions ADD CONSTRAINT chk_onboarding_org_session_flow CHECK (flow = 'organization')");
        $this->addSql("ALTER TABLE organization_onboarding_sessions ADD CONSTRAINT chk_onboarding_org_session_state CHECK (state IN ('in_progress', 'completed', 'blocked'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_onboarding_sessions DROP CONSTRAINT IF EXISTS chk_onboarding_org_session_state');
        $this->addSql('ALTER TABLE organization_onboarding_sessions DROP CONSTRAINT IF EXISTS chk_onboarding_org_session_flow');
        $this->addSql('DROP TABLE organization_onboarding_sessions');
    }
}
