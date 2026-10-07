<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add organization-owned maintenance requests and immutable conversion receipts on main.
 *
 * @category Migration
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20261006110000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add maintenance service requests and their idempotent conversion receipts.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql("CREATE TABLE service_requests (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, equipment_id VARCHAR(36) DEFAULT NULL, site_id VARCHAR(36) DEFAULT NULL, target_snapshot JSONB NOT NULL, title VARCHAR(160) NOT NULL, description TEXT NOT NULL, priority VARCHAR(16) DEFAULT 'normal' NOT NULL, origin_inspection_id VARCHAR(36) DEFAULT NULL, origin_non_conformity_id VARCHAR(36) DEFAULT NULL, status VARCHAR(16) DEFAULT 'requested' NOT NULL, revision INT DEFAULT 1 NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, qualified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rejected_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, converted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, decision_reason TEXT DEFAULT NULL, qualification_note TEXT DEFAULT NULL, intervention_id VARCHAR(36) DEFAULT NULL, task_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id), CONSTRAINT chk_service_request_target CHECK (equipment_id IS NOT NULL OR site_id IS NOT NULL), CONSTRAINT chk_service_request_revision CHECK (revision >= 1), CONSTRAINT chk_service_request_priority CHECK (priority IN ('low', 'normal', 'high', 'urgent')), CONSTRAINT chk_service_request_status CHECK (status IN ('requested', 'qualified', 'rejected', 'cancelled', 'converted')), CONSTRAINT chk_service_request_qualified_equipment CHECK (status NOT IN ('qualified', 'converted') OR equipment_id IS NOT NULL), CONSTRAINT chk_service_request_conversion CHECK (status <> 'converted' OR (intervention_id IS NOT NULL AND task_id IS NOT NULL AND converted_at IS NOT NULL)))");
    $this->addSql('CREATE INDEX idx_service_request_org_requested ON service_requests (organization_id, requested_at)');
    $this->addSql('CREATE INDEX idx_service_request_org_status ON service_requests (organization_id, status, requested_at)');
    $this->addSql('CREATE INDEX idx_service_request_org_equipment ON service_requests (organization_id, equipment_id)');
    $this->addSql('CREATE INDEX idx_service_request_org_site ON service_requests (organization_id, site_id)');
    foreach (['requested_at', 'updated_at', 'qualified_at', 'rejected_at', 'cancelled_at', 'converted_at'] as $column) {
      $this->addSql('COMMENT ON COLUMN service_requests.' . $column . " IS '(DC2Type:datetime_immutable)'");
    }
    $this->addSql('CREATE TABLE service_request_conversion_receipts (request_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, client_operation_id VARCHAR(36) NOT NULL, payload_hash VARCHAR(64) NOT NULL, intervention_id VARCHAR(36) NOT NULL, task_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(request_id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_service_request_conversion_operation ON service_request_conversion_receipts (organization_id, client_operation_id)');
    $this->addSql("COMMENT ON COLUMN service_request_conversion_receipts.created_at IS '(DC2Type:datetime_immutable)'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE service_request_conversion_receipts');
    $this->addSql('DROP TABLE service_requests');
  }
}
