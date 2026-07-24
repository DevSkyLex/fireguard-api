<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

use function sprintf;

/**
 * Domain Version20260611090000.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260611090000 extends AbstractMigration
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
    return 'Add field missions, publications, and draft metadata to operational resources';
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
    $this->addSql('CREATE TABLE missions (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, type VARCHAR(32) NOT NULL, name VARCHAR(160) NOT NULL, status VARCHAR(24) NOT NULL, reference_pack_id VARCHAR(80) NOT NULL, revision INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_mission_organization_status ON missions (organization_id, status)');
    $this->addSql('ALTER TABLE missions ADD CONSTRAINT fk_mission_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('CREATE TABLE mission_publications (id VARCHAR(36) NOT NULL, mission_id VARCHAR(36) NOT NULL, mission_revision INT NOT NULL, status VARCHAR(16) NOT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_publication_mission_revision ON mission_publications (mission_id, mission_revision)');
    $this->addSql('ALTER TABLE mission_publications ADD CONSTRAINT fk_publication_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql("CREATE TABLE inspection_responses (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, mission_id VARCHAR(36) DEFAULT NULL, inspection_id VARCHAR(36) NOT NULL, client_id VARCHAR(36) DEFAULT NULL, record_status VARCHAR(16) DEFAULT 'published' NOT NULL, revision INT DEFAULT 1 NOT NULL, item_key VARCHAR(160) NOT NULL, value JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
    $this->addSql('CREATE INDEX idx_response_inspection ON inspection_responses (inspection_id)');
    $this->addSql('CREATE INDEX idx_response_mission_status ON inspection_responses (mission_id, record_status)');
    $this->addSql('CREATE UNIQUE INDEX uniq_inspection_response_client_id ON inspection_responses (client_id)');
    $this->addSql('ALTER TABLE inspection_responses ADD CONSTRAINT fk_response_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE inspection_responses ADD CONSTRAINT fk_response_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE inspection_responses ADD CONSTRAINT fk_response_inspection FOREIGN KEY (inspection_id) REFERENCES inspections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

    foreach (['facilities', 'equipment', 'inspections'] as $table) {
      $this->addSql(sprintf("ALTER TABLE %s ADD mission_id VARCHAR(36) DEFAULT NULL, ADD client_id VARCHAR(36) DEFAULT NULL, ADD record_status VARCHAR(16) DEFAULT 'published' NOT NULL, ADD revision INT DEFAULT 1 NOT NULL", $table));
      $this->addSql(sprintf('CREATE INDEX idx_%s_mission_record_status ON %s (mission_id, record_status)', 'facilities' === $table ? 'facility' : ('inspections' === $table ? 'inspection' : 'equipment'), $table));
      $this->addSql(sprintf('CREATE UNIQUE INDEX uniq_%s_client_id ON %s (client_id)', 'facilities' === $table ? 'facility' : ('inspections' === $table ? 'inspection' : 'equipment'), $table));
      $this->addSql(sprintf('ALTER TABLE %s ADD CONSTRAINT fk_%s_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE', $table, 'facilities' === $table ? 'facility' : ('inspections' === $table ? 'inspection' : 'equipment')));
    }
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
    foreach (['facilities', 'equipment', 'inspections'] as $table) {
      $this->addSql(sprintf('ALTER TABLE %s DROP mission_id, DROP client_id, DROP record_status, DROP revision', $table));
    }
    $this->addSql('DROP TABLE inspection_responses');
    $this->addSql('DROP TABLE mission_publications');
    $this->addSql('DROP TABLE missions');
  }
}
