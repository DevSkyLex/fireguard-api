<?php

declare(strict_types=1);
namespace DoctrineMigrations\Main;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921233000 extends AbstractMigration
{
  public function getDescription(): string { return 'Fence assistant generation attempts, deadlines and original question references.'; }
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE assistant_messages ADD attempt_id VARCHAR(36) DEFAULT NULL, ADD attempt_number INT DEFAULT 0 NOT NULL, ADD attempt_sequence INT DEFAULT 0 NOT NULL, ADD attempt_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD question_message_id VARCHAR(36) DEFAULT NULL, ADD temperature DOUBLE PRECISION DEFAULT NULL');
  }
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE assistant_messages DROP attempt_id, DROP attempt_number, DROP attempt_sequence, DROP attempt_expires_at, DROP question_message_id, DROP temperature');
  }
}
