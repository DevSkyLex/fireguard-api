<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create equipment_maintenance_logs table for tracking maintenance windows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE equipment_maintenance_logs (
                id VARCHAR(36) NOT NULL,
                equipment_id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ");
        $this->addSql('CREATE INDEX idx_maintenance_log_equipment ON equipment_maintenance_logs (equipment_id)');
        $this->addSql('CREATE INDEX idx_maintenance_log_organization ON equipment_maintenance_logs (organization_id)');
        $this->addSql('CREATE INDEX idx_maintenance_log_started_at ON equipment_maintenance_logs (started_at)');
        $this->addSql('ALTER TABLE equipment_maintenance_logs ADD CONSTRAINT FK_maintenance_log_equipment FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_maintenance_logs DROP CONSTRAINT FK_maintenance_log_equipment');
        $this->addSql('DROP TABLE equipment_maintenance_logs');
    }
}
