<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20261006090000.
 *
 * Adds an operational profile while retaining the operator default for existing organizations.
 *
 * @category Migration
 */
final class Version20261006090000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add operator and service-provider organization profiles.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql("ALTER TABLE organizations ADD operating_profile VARCHAR(32) DEFAULT 'operator' NOT NULL");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE organizations DROP operating_profile');
  }
}
