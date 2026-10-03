<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20261003110000.
 * @category Migration
 * @version 1.0.0
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20261003110000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add revisioned GLB facility models with a unique active model per building';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE facility_models (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, building_id VARCHAR(36) NOT NULL, file_name VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, file_size INT NOT NULL, nodes JSONB NOT NULL, revision INT NOT NULL, active BOOLEAN NOT NULL, active_building_id VARCHAR(36) DEFAULT NULL, transform JSONB NOT NULL, bindings JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_facility_model_building ON facility_models (building_id)');
    $this->addSql('CREATE INDEX idx_facility_model_organization ON facility_models (organization_id)');
    $this->addSql('CREATE UNIQUE INDEX uniq_facility_model_storage_path ON facility_models (storage_path)');
    $this->addSql('CREATE UNIQUE INDEX uniq_facility_model_active_building ON facility_models (active_building_id)');
    $this->addSql('ALTER TABLE facility_models ADD CONSTRAINT FK_FACILITY_MODEL_BUILDING FOREIGN KEY (building_id) REFERENCES facilities (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql("COMMENT ON COLUMN facility_models.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN facility_models.updated_at IS '(DC2Type:datetime_immutable)'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE facility_models');
  }
}
