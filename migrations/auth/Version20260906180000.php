<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Persists the primary method used for a completed user sign-in.
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260906180000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add the last completed sign-in method to users';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE users ADD last_sign_in_method VARCHAR(20) DEFAULT NULL');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE users DROP last_sign_in_method');
  }
}
