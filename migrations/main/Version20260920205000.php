<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920205000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Link checklist revisions without changing inspection references.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE checklists ADD previous_checklist_id VARCHAR(36) DEFAULT NULL');
    $this->addSql('CREATE INDEX idx_checklist_previous ON checklists (previous_checklist_id)');
    $this->addSql('ALTER TABLE checklists ADD CONSTRAINT fk_checklist_previous FOREIGN KEY (previous_checklist_id) REFERENCES checklists (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE checklists DROP CONSTRAINT fk_checklist_previous');
    $this->addSql('DROP INDEX idx_checklist_previous');
    $this->addSql('ALTER TABLE checklists DROP previous_checklist_id');
  }
}
