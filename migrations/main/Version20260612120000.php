<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260612120000.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260612120000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * Executes the get description operation.
   *
   * @since 1.0.0
   *
   * @return string the get description result
   */
  public function getDescription(): string
  {
    return 'Add multi-type mission planning, work items, and proposed changes';
  }

  /**
   * Method up.
   *
   * Executes the up operation.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE missions ALTER status TYPE VARCHAR(24)');
    $this->addSql("ALTER TABLE missions ADD site_id VARCHAR(36) DEFAULT NULL, ADD responsible_id VARCHAR(36) DEFAULT NULL, ADD participants JSON DEFAULT '[]' NOT NULL, ADD priority VARCHAR(16) DEFAULT 'normal' NOT NULL, ADD planned_start_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD review_note TEXT DEFAULT NULL");
    $this->addSql('CREATE INDEX idx_mission_responsible_status ON missions (responsible_id, status)');
    $this->addSql('CREATE INDEX idx_mission_site ON missions (site_id)');
    $this->addSql('CREATE INDEX idx_mission_participants ON missions USING GIN ((participants::jsonb))');
    $this->addSql('CREATE TABLE mission_work_items (id VARCHAR(36) NOT NULL, mission_id VARCHAR(36) NOT NULL, action VARCHAR(24) NOT NULL, target VARCHAR(255) DEFAULT NULL, result_resource VARCHAR(255) DEFAULT NULL, assignee_id VARCHAR(36) DEFAULT NULL, source VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, required BOOLEAN NOT NULL, skip_reason TEXT DEFAULT NULL, revision INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_mission_work_item_mission_status ON mission_work_items (mission_id, status)');
    $this->addSql('CREATE INDEX idx_mission_work_item_assignee ON mission_work_items (assignee_id)');
    $this->addSql('ALTER TABLE mission_work_items ADD CONSTRAINT fk_mission_work_item_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('CREATE TABLE mission_changes (id VARCHAR(36) NOT NULL, mission_id VARCHAR(36) NOT NULL, work_item_id VARCHAR(36) DEFAULT NULL, resource VARCHAR(255) NOT NULL, patch JSON NOT NULL, status VARCHAR(16) NOT NULL, revision INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_mission_change_mission_status ON mission_changes (mission_id, status)');
    $this->addSql('ALTER TABLE mission_changes ADD CONSTRAINT fk_mission_change_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE mission_changes ADD CONSTRAINT fk_mission_change_work_item FOREIGN KEY (work_item_id) REFERENCES mission_work_items (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
  }

  /**
   * Method down.
   *
   * Executes the down operation.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE mission_changes');
    $this->addSql('DROP TABLE mission_work_items');
    $this->addSql('ALTER TABLE missions DROP site_id, DROP responsible_id, DROP participants, DROP priority, DROP planned_start_at, DROP due_at, DROP review_note');
    $this->addSql("UPDATE missions SET status = 'in_progress' WHERE status = 'changes_requested'");
    $this->addSql('ALTER TABLE missions ALTER status TYPE VARCHAR(16)');
  }
}
