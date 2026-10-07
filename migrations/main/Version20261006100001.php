<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20261006100001.
 *
 * Adds organization-specific equipment types; defaults stay available for future organizations.
 *
 * @category Migration
 */
final class Version20261006100001 extends AbstractMigration
{
  // #region Methods
  /** Method getDescription. */
  public function getDescription(): string
  {
    return 'Add organization equipment type catalogs with immutable codes and optimistic revisions';
  }

  /** Method up. */
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE equipment_type_catalog (organization_id VARCHAR(36) NOT NULL, type_code VARCHAR(32) NOT NULL, label VARCHAR(100) NOT NULL, family VARCHAR(16) NOT NULL, archived BOOLEAN NOT NULL, revision INT NOT NULL, PRIMARY KEY(organization_id, type_code))');
  }

  /** Method down. */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE equipment_type_catalog');
  }
  // #endregion
}
