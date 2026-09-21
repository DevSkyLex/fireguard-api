<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds encrypted TOTP storage without removing legacy columns. */
final class Version20260920150000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add versioned encrypted TOTP secrets and a durable no-downgrade marker in auth';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE totp_enrollments ADD active_secret_ciphertext TEXT DEFAULT NULL, ADD pending_secret_ciphertext TEXT DEFAULT NULL, ADD secrets_encrypted BOOLEAN DEFAULT FALSE NOT NULL');
  }

  public function down(Schema $schema): void
  {
    $encrypted = $this->connection->fetchOne('SELECT COUNT(*) FROM totp_enrollments WHERE secrets_encrypted = TRUE');
    $this->abortIf((int) $encrypted > 0, 'Encrypted TOTP enrollments exist. Keep a reader-compatible application and schema when rolling back.');
    $this->addSql('ALTER TABLE totp_enrollments DROP active_secret_ciphertext, DROP pending_secret_ciphertext, DROP secrets_encrypted');
  }
}
