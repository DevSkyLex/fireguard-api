<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260614130000.
 *
 * Renames the "organization.missions.*" permissions to
 * "organization.interventions.*" in place, preserving permission ids and the
 * role assignments that reference them.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260614130000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the get description result
   */
  public function getDescription(): string
  {
    return 'Rename organization.missions.* permissions to organization.interventions.*';
  }

  /**
   * Method up.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->addSql(
      "UPDATE permissions SET name = REPLACE(name, 'organization.missions.', 'organization.interventions.') "
      . "WHERE name LIKE 'organization.missions.%'",
    );
  }

  /**
   * Method down.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->addSql(
      "UPDATE permissions SET name = REPLACE(name, 'organization.interventions.', 'organization.missions.') "
      . "WHERE name LIKE 'organization.interventions.%'",
    );
  }
}
