<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organization invitations with role assignments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_invitations (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, email VARCHAR(320) NOT NULL, token_hash VARCHAR(64) NOT NULL, invited_by_user_id VARCHAR(36) NOT NULL, accepted_by_user_id VARCHAR(36) DEFAULT NULL, revoked_by_user_id VARCHAR(36) DEFAULT NULL, status VARCHAR(20) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_organization_invitation_organization ON organization_invitations (organization_id)');
        $this->addSql('CREATE INDEX idx_organization_invitation_email ON organization_invitations (email)');
        $this->addSql('CREATE INDEX idx_organization_invitation_status ON organization_invitations (status)');
        $this->addSql('CREATE INDEX idx_organization_invitation_organization_status ON organization_invitations (organization_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_invitation_token_hash ON organization_invitations (token_hash)');
        $this->addSql("COMMENT ON COLUMN organization_invitations.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN organization_invitations.accepted_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN organization_invitations.revoked_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN organization_invitations.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN organization_invitations.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE organization_invitation_roles (invitation_id VARCHAR(36) NOT NULL, role_id VARCHAR(36) NOT NULL, assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(invitation_id, role_id))');
        $this->addSql('CREATE INDEX idx_organization_invitation_roles_role ON organization_invitation_roles (role_id)');
        $this->addSql("COMMENT ON COLUMN organization_invitation_roles.assigned_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE organization_invitations ADD CONSTRAINT fk_organization_invitation_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_invitation_roles ADD CONSTRAINT fk_organization_invitation_role_invitation FOREIGN KEY (invitation_id) REFERENCES organization_invitations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_invitation_roles ADD CONSTRAINT fk_organization_invitation_role_role FOREIGN KEY (role_id) REFERENCES organization_roles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_invitation_roles DROP CONSTRAINT fk_organization_invitation_role_invitation');
        $this->addSql('ALTER TABLE organization_invitation_roles DROP CONSTRAINT fk_organization_invitation_role_role');
        $this->addSql('ALTER TABLE organization_invitations DROP CONSTRAINT fk_organization_invitation_organization');

        $this->addSql('DROP TABLE organization_invitation_roles');
        $this->addSql('DROP TABLE organization_invitations');
    }
}
