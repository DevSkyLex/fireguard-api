<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002031100 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Move import row reports from repeatedly rewritten job JSON into append-only paginated rows.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE import_row_reports (import_job_id VARCHAR(36) NOT NULL, row_number INT NOT NULL, code VARCHAR(32) NOT NULL, message TEXT NOT NULL, column_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(import_job_id, row_number))');
    $this->addSql("INSERT INTO import_row_reports (import_job_id, row_number, code, message, column_name)
      SELECT j.id, (report->>'rowNumber')::INTEGER, report->>'code', report->>'message', report->>'column'
      FROM import_jobs j CROSS JOIN LATERAL jsonb_array_elements(COALESCE(j.error_report::jsonb, '[]'::jsonb)) report
      ON CONFLICT DO NOTHING");
    $this->addSql('UPDATE import_jobs SET error_report = NULL WHERE error_report IS NOT NULL');
  }

  public function down(Schema $schema): void
  {
    $this->addSql("UPDATE import_jobs j SET error_report = reports.report FROM
      (SELECT import_job_id, json_agg(json_build_object('rowNumber', row_number, 'code', code, 'message', message, 'column', column_name) ORDER BY row_number) AS report
       FROM import_row_reports GROUP BY import_job_id) reports WHERE reports.import_job_id = j.id");
    $this->addSql('DROP TABLE import_row_reports');
  }
}
