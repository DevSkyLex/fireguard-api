<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260717134458.
 *
 * R13 — bulk CSV import. Adds the `import_jobs` table (main DB): one row
 * per uploaded CSV batch, tracking status/progress/error-report for
 * Equipment/Facility bulk provisioning. `organization_id` is a plain column
 * (not an ORM association) with its foreign key added directly here,
 * mirroring `intervention_attachments.work_item_id`'s precedent
 * (`Version20260717111309`).
 *
 * Hand-pruned from the raw `doctrine:migrations:diff` output: only the
 * `import_jobs` table and its indexes/FK are kept here — unrelated
 * cross-entity-manager drift (auth-database tables, pre-existing schema
 * differences from other in-flight lots) is intentionally dropped.
 */
final class Version20260717134458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R13: add import_jobs table (bulk CSV import batches).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE import_jobs (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, kind VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, storage_path VARCHAR(512) NOT NULL, original_filename VARCHAR(255) NOT NULL, total_rows INT DEFAULT NULL, processed_rows INT NOT NULL, successful_rows INT NOT NULL, failed_rows INT NOT NULL, error_report JSON DEFAULT NULL, job_error TEXT DEFAULT NULL, created_by VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_import_job_org_status ON import_jobs (organization_id, status)');
        $this->addSql('CREATE INDEX idx_import_job_created ON import_jobs (created_at)');
        $this->addSql('COMMENT ON COLUMN import_jobs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN import_jobs.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN import_jobs.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN import_jobs.completed_at IS \'(DC2Type:datetime_immutable)\'');
        // organization_id is a plain column (see class docblock): the FK is added
        // directly here, not through an ORM association.
        $this->addSql('ALTER TABLE import_jobs ADD CONSTRAINT FK_import_jobs_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_jobs DROP CONSTRAINT FK_import_jobs_organization_id');
        $this->addSql('DROP TABLE import_jobs');
    }
}
