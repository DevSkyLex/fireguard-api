<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916090509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable task effort and periods, independent time history and effective-dated workload capacity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE intervention_time_entries (id VARCHAR(36) NOT NULL, work_item_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, worked_on VARCHAR(10) NOT NULL, minutes INT NOT NULL, note TEXT DEFAULT NULL, cancelled BOOLEAN NOT NULL, revision INT NOT NULL, created_by VARCHAR(36) NOT NULL, updated_by VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7C01B631C7A4EA7C ON intervention_time_entries (work_item_id)');
        $this->addSql('CREATE INDEX idx_time_entry_member_date ON intervention_time_entries (organization_id, member_id, worked_on)');
        $this->addSql('COMMENT ON COLUMN intervention_time_entries.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN intervention_time_entries.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE intervention_time_entry_versions (revision INT NOT NULL, entry_id VARCHAR(36) NOT NULL, worked_on VARCHAR(10) NOT NULL, minutes INT NOT NULL, note TEXT DEFAULT NULL, cancelled BOOLEAN NOT NULL, actor_id VARCHAR(36) NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(entry_id, revision))');
        $this->addSql('CREATE INDEX IDX_81F27508BA364942 ON intervention_time_entry_versions (entry_id)');
        $this->addSql('COMMENT ON COLUMN intervention_time_entry_versions.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE intervention_work_item_assignments (id VARCHAR(36) NOT NULL, work_item_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, unassigned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, actor_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D3E1903AC7A4EA7C ON intervention_work_item_assignments (work_item_id)');
        $this->addSql('CREATE INDEX idx_work_item_assignment_member ON intervention_work_item_assignments (work_item_id, member_id)');
        $this->addSql('COMMENT ON COLUMN intervention_work_item_assignments.assigned_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN intervention_work_item_assignments.unassigned_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE workload_capacity_exceptions (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, starts_on VARCHAR(10) NOT NULL, ends_on VARCHAR(10) NOT NULL, minutes INT NOT NULL, created_by VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_by VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_capacity_exception_member_date ON workload_capacity_exceptions (organization_id, member_id, starts_on, ends_on)');
        $this->addSql('COMMENT ON COLUMN workload_capacity_exceptions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN workload_capacity_exceptions.cancelled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE workload_capacity_weeks (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, scope_id VARCHAR(36) NOT NULL, effective_on VARCHAR(10) NOT NULL, minutes JSON NOT NULL, created_by VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_capacity_scope_date ON workload_capacity_weeks (organization_id, scope_id, effective_on)');
        $this->addSql('COMMENT ON COLUMN workload_capacity_weeks.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE intervention_time_entries ADD CONSTRAINT FK_7C01B631C7A4EA7C FOREIGN KEY (work_item_id) REFERENCES intervention_work_items (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE intervention_time_entry_versions ADD CONSTRAINT FK_81F27508BA364942 FOREIGN KEY (entry_id) REFERENCES intervention_time_entries (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE intervention_work_item_assignments ADD CONSTRAINT FK_D3E1903AC7A4EA7C FOREIGN KEY (work_item_id) REFERENCES intervention_work_items (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE intervention_template_items ADD estimated_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE intervention_work_items ADD estimated_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE intervention_work_items ADD remaining_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE intervention_work_items ADD work_starts_on VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE intervention_work_items ADD work_ends_on VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE intervention_work_items ADD CONSTRAINT chk_work_item_minutes CHECK ((estimated_minutes IS NULL OR estimated_minutes >= 0) AND (remaining_minutes IS NULL OR remaining_minutes >= 0))');
        $this->addSql('ALTER TABLE intervention_time_entries ADD CONSTRAINT chk_time_entry_minutes CHECK (minutes BETWEEN 1 AND 1440 AND revision > 0)');
        $this->addSql('ALTER TABLE intervention_time_entry_versions ADD CONSTRAINT chk_time_version_minutes CHECK (minutes BETWEEN 1 AND 1440 AND revision > 0)');
        $this->addSql('ALTER TABLE workload_capacity_exceptions ADD CONSTRAINT chk_capacity_exception CHECK (minutes BETWEEN 0 AND 1440 AND starts_on <= ends_on)');
        // Snapshot current assignments, without inventing their historical start date.
        $this->addSql('INSERT INTO intervention_work_item_assignments (id, work_item_id, member_id, assigned_at) SELECT id, id, assignee_id, CURRENT_TIMESTAMP FROM intervention_work_items WHERE assignee_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention_time_entries DROP CONSTRAINT FK_7C01B631C7A4EA7C');
        $this->addSql('ALTER TABLE intervention_time_entry_versions DROP CONSTRAINT FK_81F27508BA364942');
        $this->addSql('ALTER TABLE intervention_work_item_assignments DROP CONSTRAINT FK_D3E1903AC7A4EA7C');
        $this->addSql('DROP TABLE intervention_time_entries');
        $this->addSql('DROP TABLE intervention_time_entry_versions');
        $this->addSql('DROP TABLE intervention_work_item_assignments');
        $this->addSql('DROP TABLE workload_capacity_exceptions');
        $this->addSql('DROP TABLE workload_capacity_weeks');
        $this->addSql('ALTER TABLE intervention_work_items DROP estimated_minutes');
        $this->addSql('ALTER TABLE intervention_work_items DROP remaining_minutes');
        $this->addSql('ALTER TABLE intervention_work_items DROP work_starts_on');
        $this->addSql('ALTER TABLE intervention_work_items DROP work_ends_on');
        $this->addSql('ALTER TABLE intervention_template_items DROP estimated_minutes');
    }
}
