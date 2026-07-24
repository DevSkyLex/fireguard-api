<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

use function sprintf;

/**
 * Domain Version20260614120000.
 *
 * Renames the "mission" domain to "intervention": tables, columns, indexes,
 * unique constraints and foreign keys across the intervention context and the
 * facility/equipment/inspection resources that reference it.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260614120000 extends AbstractMigration
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
    return 'Rename mission domain to intervention (tables, columns, indexes, constraints)';
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
    // Tables
    $this->addSql('ALTER TABLE missions RENAME TO interventions');
    $this->addSql('ALTER TABLE mission_publications RENAME TO intervention_publications');
    $this->addSql('ALTER TABLE mission_work_items RENAME TO intervention_work_items');
    $this->addSql('ALTER TABLE mission_changes RENAME TO intervention_changes');

    // interventions
    $this->addSql('ALTER INDEX idx_mission_organization_status RENAME TO idx_intervention_organization_status');
    $this->addSql('ALTER INDEX idx_mission_responsible_status RENAME TO idx_intervention_responsible_status');
    $this->addSql('ALTER INDEX idx_mission_site RENAME TO idx_intervention_site');
    $this->addSql('ALTER INDEX idx_mission_participants RENAME TO idx_intervention_participants');
    $this->addSql('ALTER TABLE interventions RENAME CONSTRAINT fk_mission_organization TO fk_intervention_organization');

    // intervention_publications
    $this->addSql('ALTER TABLE intervention_publications RENAME COLUMN mission_id TO intervention_id');
    $this->addSql('ALTER TABLE intervention_publications RENAME COLUMN mission_revision TO intervention_revision');
    $this->addSql('ALTER INDEX uniq_publication_mission_revision RENAME TO uniq_publication_intervention_revision');
    $this->addSql('ALTER TABLE intervention_publications RENAME CONSTRAINT fk_publication_mission TO fk_publication_intervention');

    // intervention_work_items
    $this->addSql('ALTER TABLE intervention_work_items RENAME COLUMN mission_id TO intervention_id');
    $this->addSql('ALTER INDEX idx_mission_work_item_mission_status RENAME TO idx_intervention_work_item_intervention_status');
    $this->addSql('ALTER INDEX idx_mission_work_item_assignee RENAME TO idx_intervention_work_item_assignee');
    $this->addSql('ALTER TABLE intervention_work_items RENAME CONSTRAINT fk_mission_work_item_mission TO fk_intervention_work_item_intervention');

    // intervention_changes
    $this->addSql('ALTER TABLE intervention_changes RENAME COLUMN mission_id TO intervention_id');
    $this->addSql('ALTER INDEX idx_mission_change_mission_status RENAME TO idx_intervention_change_intervention_status');
    $this->addSql('ALTER TABLE intervention_changes RENAME CONSTRAINT fk_mission_change_mission TO fk_intervention_change_intervention');
    $this->addSql('ALTER TABLE intervention_changes RENAME CONSTRAINT fk_mission_change_work_item TO fk_intervention_change_work_item');

    // inspection_responses (references intervention)
    $this->addSql('ALTER TABLE inspection_responses RENAME COLUMN mission_id TO intervention_id');
    $this->addSql('ALTER INDEX idx_response_mission_status RENAME TO idx_response_intervention_status');
    $this->addSql('ALTER TABLE inspection_responses RENAME CONSTRAINT fk_response_mission TO fk_response_intervention');

    // facilities / equipment / inspections (draft resources referencing intervention)
    foreach (['facility' => 'facilities', 'equipment' => 'equipment', 'inspection' => 'inspections'] as $prefix => $table) {
      $this->addSql(sprintf('ALTER TABLE %s RENAME COLUMN mission_id TO intervention_id', $table));
      $this->addSql(sprintf('ALTER INDEX idx_%s_mission_record_status RENAME TO idx_%s_intervention_record_status', $prefix, $prefix));
      $this->addSql(sprintf('ALTER TABLE %s RENAME CONSTRAINT fk_%s_mission TO fk_%s_intervention', $table, $prefix, $prefix));
    }
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
    foreach (['facility' => 'facilities', 'equipment' => 'equipment', 'inspection' => 'inspections'] as $prefix => $table) {
      $this->addSql(sprintf('ALTER TABLE %s RENAME CONSTRAINT fk_%s_intervention TO fk_%s_mission', $table, $prefix, $prefix));
      $this->addSql(sprintf('ALTER INDEX idx_%s_intervention_record_status RENAME TO idx_%s_mission_record_status', $prefix, $prefix));
      $this->addSql(sprintf('ALTER TABLE %s RENAME COLUMN intervention_id TO mission_id', $table));
    }

    $this->addSql('ALTER TABLE inspection_responses RENAME CONSTRAINT fk_response_intervention TO fk_response_mission');
    $this->addSql('ALTER INDEX idx_response_intervention_status RENAME TO idx_response_mission_status');
    $this->addSql('ALTER TABLE inspection_responses RENAME COLUMN intervention_id TO mission_id');

    $this->addSql('ALTER TABLE intervention_changes RENAME CONSTRAINT fk_intervention_change_work_item TO fk_mission_change_work_item');
    $this->addSql('ALTER TABLE intervention_changes RENAME CONSTRAINT fk_intervention_change_intervention TO fk_mission_change_mission');
    $this->addSql('ALTER INDEX idx_intervention_change_intervention_status RENAME TO idx_mission_change_mission_status');
    $this->addSql('ALTER TABLE intervention_changes RENAME COLUMN intervention_id TO mission_id');

    $this->addSql('ALTER TABLE intervention_work_items RENAME CONSTRAINT fk_intervention_work_item_intervention TO fk_mission_work_item_mission');
    $this->addSql('ALTER INDEX idx_intervention_work_item_assignee RENAME TO idx_mission_work_item_assignee');
    $this->addSql('ALTER INDEX idx_intervention_work_item_intervention_status RENAME TO idx_mission_work_item_mission_status');
    $this->addSql('ALTER TABLE intervention_work_items RENAME COLUMN intervention_id TO mission_id');

    $this->addSql('ALTER TABLE intervention_publications RENAME CONSTRAINT fk_publication_intervention TO fk_publication_mission');
    $this->addSql('ALTER INDEX uniq_publication_intervention_revision RENAME TO uniq_publication_mission_revision');
    $this->addSql('ALTER TABLE intervention_publications RENAME COLUMN intervention_revision TO mission_revision');
    $this->addSql('ALTER TABLE intervention_publications RENAME COLUMN intervention_id TO mission_id');

    $this->addSql('ALTER TABLE interventions RENAME CONSTRAINT fk_intervention_organization TO fk_mission_organization');
    $this->addSql('ALTER INDEX idx_intervention_participants RENAME TO idx_mission_participants');
    $this->addSql('ALTER INDEX idx_intervention_site RENAME TO idx_mission_site');
    $this->addSql('ALTER INDEX idx_intervention_responsible_status RENAME TO idx_mission_responsible_status');
    $this->addSql('ALTER INDEX idx_intervention_organization_status RENAME TO idx_mission_organization_status');

    $this->addSql('ALTER TABLE intervention_changes RENAME TO mission_changes');
    $this->addSql('ALTER TABLE intervention_work_items RENAME TO mission_work_items');
    $this->addSql('ALTER TABLE intervention_publications RENAME TO mission_publications');
    $this->addSql('ALTER TABLE interventions RENAME TO missions');
  }
}
