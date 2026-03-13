<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align main foreign key constraint names with current Doctrine mapping';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('ALTER TABLE organization_roles RENAME CONSTRAINT fk_organization_role_organization TO FK_38B9C80032C8A3DE');
        $this->addSql('ALTER TABLE organization_members RENAME CONSTRAINT fk_organization_member_organization TO FK_88725ABC32C8A3DE');
        $this->addSql('ALTER TABLE organization_member_roles RENAME CONSTRAINT fk_organization_member_role_member TO FK_13F84F247597D3FE');
        $this->addSql('ALTER TABLE organization_member_roles RENAME CONSTRAINT fk_organization_member_role_role TO FK_13F84F24D60322AC');
        $this->addSql('ALTER TABLE organization_invitations RENAME CONSTRAINT fk_organization_invitation_organization TO FK_137BB4D532C8A3DE');
        $this->addSql('ALTER TABLE organization_invitation_roles RENAME CONSTRAINT fk_organization_invitation_role_invitation TO FK_2C1E044DA35D7AF0');
        $this->addSql('ALTER TABLE organization_invitation_roles RENAME CONSTRAINT fk_organization_invitation_role_role TO FK_2C1E044DD60322AC');
        $this->addSql('ALTER TABLE organization_legal_profile RENAME CONSTRAINT fk_organization_legal_profile_organization TO FK_5B1CC3F132C8A3DE');
        $this->addSql('ALTER TABLE facilities RENAME CONSTRAINT fk_facility_organization TO FK_ADE885D532C8A3DE');
        $this->addSql('ALTER TABLE facilities RENAME CONSTRAINT fk_facility_parent TO FK_ADE885D5CC2D5B60');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('ALTER TABLE organization_roles RENAME CONSTRAINT FK_38B9C80032C8A3DE TO fk_organization_role_organization');
        $this->addSql('ALTER TABLE organization_members RENAME CONSTRAINT FK_88725ABC32C8A3DE TO fk_organization_member_organization');
        $this->addSql('ALTER TABLE organization_member_roles RENAME CONSTRAINT FK_13F84F247597D3FE TO fk_organization_member_role_member');
        $this->addSql('ALTER TABLE organization_member_roles RENAME CONSTRAINT FK_13F84F24D60322AC TO fk_organization_member_role_role');
        $this->addSql('ALTER TABLE organization_invitations RENAME CONSTRAINT FK_137BB4D532C8A3DE TO fk_organization_invitation_organization');
        $this->addSql('ALTER TABLE organization_invitation_roles RENAME CONSTRAINT FK_2C1E044DA35D7AF0 TO fk_organization_invitation_role_invitation');
        $this->addSql('ALTER TABLE organization_invitation_roles RENAME CONSTRAINT FK_2C1E044DD60322AC TO fk_organization_invitation_role_role');
        $this->addSql('ALTER TABLE organization_legal_profile RENAME CONSTRAINT FK_5B1CC3F132C8A3DE TO fk_organization_legal_profile_organization');
        $this->addSql('ALTER TABLE facilities RENAME CONSTRAINT FK_ADE885D532C8A3DE TO fk_facility_organization');
        $this->addSql('ALTER TABLE facilities RENAME CONSTRAINT FK_ADE885D5CC2D5B60 TO fk_facility_parent');
    }
}
