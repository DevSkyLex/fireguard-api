<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261007100000
 *
 * Adds retained ERP export artifacts, actor-scoped receipts and versioned external references.
 *
 * @category Migration
 */
final class Version20261007100000 extends AbstractMigration
{
  // #region Methods
  /**
   * Method getDescription
   *
   * @return string additive schema purpose
   */
  public function getDescription(): string
  {
    return 'Retain immutable versioned maintenance CSV/JSON artifacts, adjustments and explicit import acknowledgements.';
  }

  /**
   * Method up
   *
   * @param Schema $schema existing main schema
   *
   * @return void creates only new module tables
   */
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE maintenance_export_documents (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, actor_id VARCHAR(36) NOT NULL, kind VARCHAR(32) NOT NULL, system VARCHAR(64) NOT NULL, include_internal_costs BOOLEAN NOT NULL, source_intervention_ids JSONB NOT NULL, original_export_id VARCHAR(36) DEFAULT NULL, adjustment_of VARCHAR(36) DEFAULT NULL, reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, rows JSONB NOT NULL, baseline JSONB NOT NULL, json_bytes TEXT NOT NULL, csv_bytes TEXT NOT NULL, json_sha256 VARCHAR(64) NOT NULL, csv_sha256 VARCHAR(64) NOT NULL, immutable_hash VARCHAR(64) NOT NULL, costs_complete BOOLEAN DEFAULT NULL, incomplete_cost_count INT DEFAULT NULL, revision INT NOT NULL, confirmation JSONB DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_export_adjustment ON maintenance_export_documents (organization_id, adjustment_of)');
    $this->addSql('CREATE INDEX idx_maintenance_export_directory ON maintenance_export_documents (organization_id, created_at, id)');
    $this->addSql("COMMENT ON COLUMN maintenance_export_documents.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql('CREATE TABLE maintenance_export_operations (organization_id VARCHAR(36) NOT NULL, actor_id VARCHAR(36) NOT NULL, client_operation_id VARCHAR(36) NOT NULL, action VARCHAR(32) NOT NULL, fingerprint VARCHAR(64) NOT NULL, resource_id VARCHAR(36) NOT NULL, result JSONB NOT NULL, PRIMARY KEY(organization_id, actor_id, client_operation_id))');
    $this->addSql('CREATE TABLE maintenance_external_references (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, system VARCHAR(64) NOT NULL, resource_type VARCHAR(32) NOT NULL, resource_id VARCHAR(36) NOT NULL, reference VARCHAR(200) NOT NULL, revision INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_maintenance_external_reference ON maintenance_external_references (organization_id, system, resource_type, resource_id)');
    $this->addSql("COMMENT ON COLUMN maintenance_external_references.updated_at IS '(DC2Type:datetime_immutable)'");
  }

  /**
   * Method down
   *
   * @param Schema $schema current main schema
   *
   * @return void removes the new module and its retained history
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE maintenance_external_references');
    $this->addSql('DROP TABLE maintenance_export_operations');
    $this->addSql('DROP TABLE maintenance_export_documents');
  }
  // #endregion
}
