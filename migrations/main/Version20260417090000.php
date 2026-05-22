<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add dashboard period indexes for facility, equipment, member, and non-conformity aggregates';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_facility_organization_created_at ON facilities (organization_id, created_at)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_equipment_organization_created_at ON equipment (organization_id, created_at)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_organization_member_organization_joined_at ON organization_members (organization_id, joined_at)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_non_conformity_created_at_inspection ON non_conformities (created_at, inspection_id)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_non_conformity_resolved_at_inspection_not_null ON non_conformities (resolved_at, inspection_id) WHERE resolved_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_non_conformity_resolved_at_inspection_not_null');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_non_conformity_created_at_inspection');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_organization_member_organization_joined_at');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_equipment_organization_created_at');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_facility_organization_created_at');
    }
}
