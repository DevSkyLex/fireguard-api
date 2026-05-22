<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331120000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add targeted dashboard analytics indexes for facilities, equipment, and filtered inspection trends';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_facility_organization_status_type ON facilities (organization_id, status, type)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_equipment_organization_type_status ON equipment (organization_id, type, status)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_inspection_organization_inspector_type_performed_at ON inspections (organization_id, inspector_type, performed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_facility_organization_status_type');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_equipment_organization_type_status');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_inspection_organization_inspector_type_performed_at');
    }
}
