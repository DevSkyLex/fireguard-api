<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns federated authentication tables with their Doctrine scalar identifiers.
 */
final class Version20260908072535 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Remove user foreign keys not represented by the federated authentication mappings';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE federated_auth_flows DROP CONSTRAINT IF EXISTS fk_federated_flow_user');
    $this->addSql('ALTER TABLE federated_identities DROP CONSTRAINT IF EXISTS fk_federated_identity_user');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE federated_auth_flows ADD CONSTRAINT fk_federated_flow_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE federated_identities ADD CONSTRAINT fk_federated_identity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
  }
}
