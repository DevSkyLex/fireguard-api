<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Binds pending federated authentication flows to the browser that created them.
 */
final class Version20260906190000 extends AbstractMigration
{
  /**
   * Returns the migration purpose shown by Doctrine tooling.
   */
  public function getDescription(): string
  {
    return 'Bind federated authentication flows to their originating browser.';
  }

  /**
   * Adds the browser binding after clearing short-lived flows created before it existed.
   */
  public function up(Schema $schema): void
  {
    $this->addSql('DELETE FROM federated_auth_flows');
    $this->addSql('ALTER TABLE federated_auth_flows ADD browser_binding_hash VARCHAR(64) NOT NULL');
  }

  /**
   * Removes the browser binding when rolling the migration back.
   */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE federated_auth_flows DROP browser_binding_hash');
  }
}
