<?php

declare(strict_types=1);
namespace DoctrineMigrations\Main;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260921234000 extends AbstractMigration
{
  public function getDescription(): string { return 'Retain automation attempts and fence explicit retries by action and attempt identity.'; }
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE automation_runs ADD trigger_payload JSONB DEFAULT NULL, ADD current_attempt_id VARCHAR(36) DEFAULT NULL, ADD attempt_count INT DEFAULT 1 NOT NULL');
    $this->addSql('CREATE TABLE automation_attempts (id VARCHAR(36) NOT NULL, run_id VARCHAR(36) NOT NULL, attempt_number INT NOT NULL, status VARCHAR(16) NOT NULL, requested_by VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_automation_attempt_run ON automation_attempts (run_id)');
    $this->addSql('CREATE UNIQUE INDEX uniq_automation_attempt_number ON automation_attempts (run_id, attempt_number)');
    $this->addSql('ALTER TABLE automation_attempts ADD CONSTRAINT fk_automation_attempt_run FOREIGN KEY (run_id) REFERENCES automation_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql("COMMENT ON COLUMN automation_attempts.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN automation_attempts.finished_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql('UPDATE automation_runs SET current_attempt_id = id');
    $this->addSql('INSERT INTO automation_attempts (id, run_id, attempt_number, status, created_at, finished_at) SELECT id, id, 1, status, created_at, created_at FROM automation_runs');
  }
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE automation_attempts');
    $this->addSql('ALTER TABLE automation_runs DROP trigger_payload, DROP current_attempt_id, DROP attempt_count');
  }
}
