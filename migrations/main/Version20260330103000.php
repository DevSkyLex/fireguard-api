<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330103000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add analytics indexes for organization dashboard inspection and non-conformity queries';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_inspection_organization_performed_at ON inspections (organization_id, performed_at)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_non_conformity_inspection_created_at ON non_conformities (inspection_id, created_at)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_non_conformity_inspection_resolved_at ON non_conformities (inspection_id, resolved_at)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_non_conformity_inspection_status_due_at ON non_conformities (inspection_id, status, due_at)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_inspection_organization_performed_at');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_non_conformity_inspection_created_at');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_non_conformity_inspection_resolved_at');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_non_conformity_inspection_status_due_at');
    }
}

