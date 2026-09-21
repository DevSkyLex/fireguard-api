<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add exclusive import leases and a receipt for every atomically committed row.
 */
final class Version20260920161323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Exclusive import worker leases and transactional row receipts';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE import_row_receipts (import_job_id VARCHAR(36) NOT NULL, row_number INT NOT NULL, outcome VARCHAR(32) NOT NULL, resource_id VARCHAR(36) DEFAULT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(import_job_id, row_number))');
        $this->addSql('COMMENT ON COLUMN import_row_receipts.confirmed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE import_jobs ADD lease_owner VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE import_jobs ADD lease_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN import_jobs.lease_expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE import_row_receipts');
        $this->addSql('ALTER TABLE import_jobs DROP lease_owner');
        $this->addSql('ALTER TABLE import_jobs DROP lease_expires_at');
    }
}
