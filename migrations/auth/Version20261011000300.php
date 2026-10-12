<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds durable owner security revisions used to refuse stale credential-response replays. */
final class Version20261011000300 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add auth user and OAuth client security revision counters for sensitive idempotency proof';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE users ADD security_revision INT DEFAULT 1 NOT NULL');
    $this->addSql('ALTER TABLE clients ADD security_revision INT DEFAULT 1 NOT NULL');
    $this->addSql('ALTER TABLE users ADD CONSTRAINT chk_users_security_revision CHECK (security_revision > 0)');
    $this->addSql('ALTER TABLE clients ADD CONSTRAINT chk_clients_security_revision CHECK (security_revision > 0)');
  }

  public function down(Schema $schema): void
  {
    $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE security_revision <> 1') > 0, 'Refusing to discard evolved user security revisions.');
    $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM clients WHERE security_revision <> 1') > 0, 'Refusing to discard evolved client security revisions.');
    $this->addSql('ALTER TABLE users DROP CONSTRAINT chk_users_security_revision');
    $this->addSql('ALTER TABLE clients DROP CONSTRAINT chk_clients_security_revision');
    $this->addSql('ALTER TABLE users DROP security_revision');
    $this->addSql('ALTER TABLE clients DROP security_revision');
  }
}
