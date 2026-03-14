<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align main schema defaults and indexes with Doctrine mappings';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('ALTER TABLE equipment ALTER COLUMN status DROP DEFAULT');
        $this->addSql('DROP INDEX IF EXISTS uniq_equipment_organization_serial');
        $this->addSql('CREATE UNIQUE INDEX uniq_equipment_organization_serial ON equipment (organization_id, serial_number)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_equipment_tag_tag ON equipment_tag (tag_id)');

        $this->addSql('ALTER TABLE inspections ALTER COLUMN status DROP DEFAULT');
        $this->addSql('ALTER TABLE non_conformities ALTER COLUMN status DROP DEFAULT');
        $this->addSql('ALTER TABLE checklists ALTER COLUMN status DROP DEFAULT');
        $this->addSql('ALTER TABLE checklist_items ALTER COLUMN required DROP DEFAULT');

        $this->addSql('ALTER TABLE organization_onboarding_sessions ALTER COLUMN skipped_steps DROP DEFAULT');
        $this->addSql('ALTER TABLE organization_onboarding_sessions ALTER COLUMN step_history DROP DEFAULT');

        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_13F84F247597D3FE ON organization_member_roles (member_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_2C1E044DA35D7AF0 ON organization_invitation_roles (invitation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql("ALTER TABLE equipment ALTER COLUMN status SET DEFAULT 'in_stock'");
        $this->addSql('DROP INDEX IF EXISTS uniq_equipment_organization_serial');
        $this->addSql('CREATE UNIQUE INDEX uniq_equipment_organization_serial ON equipment (organization_id, serial_number) WHERE serial_number IS NOT NULL');
        $this->addSql('DROP INDEX IF EXISTS idx_equipment_tag_tag');

        $this->addSql("ALTER TABLE inspections ALTER COLUMN status SET DEFAULT 'draft'");
        $this->addSql("ALTER TABLE non_conformities ALTER COLUMN status SET DEFAULT 'open'");
        $this->addSql("ALTER TABLE checklists ALTER COLUMN status SET DEFAULT 'active'");
        $this->addSql('ALTER TABLE checklist_items ALTER COLUMN required SET DEFAULT TRUE');

        $this->addSql("ALTER TABLE organization_onboarding_sessions ALTER COLUMN skipped_steps SET DEFAULT '[]'");
        $this->addSql("ALTER TABLE organization_onboarding_sessions ALTER COLUMN step_history SET DEFAULT '[]'");

        $this->addSql('DROP INDEX IF EXISTS IDX_13F84F247597D3FE');
        $this->addSql('DROP INDEX IF EXISTS IDX_2C1E044DA35D7AF0');
    }
}
