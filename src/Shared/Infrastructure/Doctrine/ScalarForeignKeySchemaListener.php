<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Class ScalarForeignKeySchemaListener
 *
 * Preserves database constraints after module-owned Records replace foreign ORM
 * associations with scalar identifiers. Schema generation must retain these keys
 * so validation and migration diffs cannot silently remove deletion guarantees.
 *
 * @category EventListener
 */
final readonly class ScalarForeignKeySchemaListener
{
  // #region Constants
  /**
   * Constant ORGANIZATION_KEYS
   *
   * Existing main-side organization keys, including their historical names.
   *
   * @var array<string, string>
   */
  private const array ORGANIZATION_KEYS = [
    'equipment' => 'FK_D338D58332C8A3DE',
    'equipment_tag_catalog' => 'FK_485FEBCA32C8A3DE',
    'facility_metadata_fields' => 'FK_37E6724C32C8A3DE',
    'facilities' => 'FK_ADE885D532C8A3DE',
    'checklists' => 'FK_B0839B3F32C8A3DE',
    'inspections' => 'FK_8625499032C8A3DE',
    'inspection_responses' => 'FK_CD1506CC32C8A3DE',
    'intervention_labels' => 'FK_5F71203432C8A3DE',
    'interventions' => 'FK_5ADBAD7F32C8A3DE',
    'intervention_recurrences' => 'FK_298BE96032C8A3DE',
    'intervention_templates' => 'FK_20CA1CA732C8A3DE',
    'maintenance_schedules' => 'FK_74FAF8FF32C8A3DE',
    'messaging_conversations' => 'FK_6489D332C8A3DE',
  ];
  // #endregion

  // #region Methods
  /**
   * Method postGenerateSchema
   *
   * Restores scalar foreign keys only when both tables belong to this schema.
   * No descriptor creates an auth/main join or changes a deletion action.
   *
   * @access public
   *
   * @param GenerateSchemaEventArgs $args the owning entity manager's schema
   *
   * @return void
   */
  public function postGenerateSchema(GenerateSchemaEventArgs $args): void
  {
    $schema = $args->getSchema();
    $this->addOrganizationKeys($schema);
    $this->addUserRoleKeys($schema);
  }

  /**
   * Method addOrganizationKeys
   *
   * Retains the existing main-side organization constraints for present owner tables.
   *
   * @access private
   *
   * @param Schema $schema the owning entity manager's schema
   *
   * @return void
   */
  private function addOrganizationKeys(Schema $schema): void
  {

    if ($schema->hasTable('organizations')) {
      foreach (self::ORGANIZATION_KEYS as $tableName => $constraintName) {
        if (!$schema->hasTable($tableName)) {
          continue;
        }

        $table = $schema->getTable($tableName);
        if (!$table->hasForeignKey($constraintName)) {
          $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], [
            'onDelete' => 'CASCADE',
            'deferrable' => false,
          ], $constraintName);
        }
      }
    }
  }

  /**
   * Method addUserRoleKeys
   *
   * Keeps auth-side membership constraints without introducing a cascade deletion.
   *
   * @access private
   *
   * @param Schema $schema the owning entity manager's schema
   *
   * @return void
   */
  private function addUserRoleKeys(Schema $schema): void
  {
    if ($schema->hasTable('user_roles') && $schema->hasTable('users') && $schema->hasTable('roles')) {
      $table = $schema->getTable('user_roles');
      foreach (['users' => ['user_id', 'FK_54FCD59FA76ED395'], 'roles' => ['role_id', 'FK_54FCD59FD60322AC']] as $target => [$column, $name]) {
        if (!$table->hasForeignKey($name)) {
          // Deliberately retain PostgreSQL's NO ACTION behavior: links are purged first.
          $table->addForeignKeyConstraint($target, [$column], ['id'], ['deferrable' => false], $name);
        }
      }
    }
  }
  // #endregion
}
