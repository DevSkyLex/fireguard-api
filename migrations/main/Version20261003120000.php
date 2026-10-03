<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261003120000
 *
 * Persists the building frame of existing image calibrations before future hierarchy changes.
 *
 * @category Migration
 */
final class Version20261003120000 extends AbstractMigration
{
  // #region Methods
  /**
   * Method getDescription
   *
   * @access public
   *
   * @return string migration purpose
   */
  public function getDescription(): string
  {
    return 'Retain the original building frame of calibrated floor-plan images.';
  }

  /**
   * Method up
   *
   * Backfills only organization-scoped published ancestry and stops repeated ancestors.
   *
   * @access public
   *
   * @param Schema $schema the schema being migrated
   *
   * @return void
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE facility_attachments ADD calibration_building_id VARCHAR(36) DEFAULT NULL');
    $this->addSql(<<<'SQL'
      WITH RECURSIVE calibrated_ancestors AS (
        SELECT attachment.id AS attachment_id, owner.id, owner.parent_facility_id,
          owner.organization_id, owner.type, 0 AS depth, ARRAY[owner.id]::VARCHAR[] AS visited
        FROM facility_attachments attachment
        INNER JOIN facilities owner ON owner.id = attachment.facility_id
        WHERE attachment.calibration IS NOT NULL
          AND attachment.kind = 'floor_plan'
          AND owner.record_status = 'published'
        UNION ALL
        SELECT ancestors.attachment_id, parent.id, parent.parent_facility_id,
          ancestors.organization_id, parent.type, ancestors.depth + 1, ancestors.visited || parent.id
        FROM calibrated_ancestors ancestors
        INNER JOIN facilities parent ON parent.id = ancestors.parent_facility_id
          AND parent.organization_id = ancestors.organization_id
          AND parent.record_status = 'published'
        WHERE NOT parent.id = ANY(ancestors.visited)
      ), frames AS (
        SELECT DISTINCT ON (attachment_id) attachment_id, id AS building_id
        FROM calibrated_ancestors
        WHERE type = 'building'
        ORDER BY attachment_id, depth ASC, id ASC
      )
      UPDATE facility_attachments attachment
      SET calibration_building_id = frames.building_id
      FROM frames
      WHERE attachment.id = frames.attachment_id
      SQL);
  }

  /**
   * Method down
   *
   * Removes provenance while preserving the raw calibration values.
   *
   * @access public
   *
   * @param Schema $schema the schema being migrated
   *
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE facility_attachments DROP calibration_building_id');
  }
  // #endregion
}
