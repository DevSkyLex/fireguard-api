<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Additive business schema for retained supplier, purchase and physical receipt evidence. */
final class Version20261006112000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add organization-scoped procurement with exact amounts and stable physical operation keys.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE procurement_suppliers (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, name VARCHAR(160) NOT NULL, code VARCHAR(64) DEFAULT NULL, email VARCHAR(254) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, contacts JSONB NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revision INT NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE TABLE procurement_orders (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, supplier_id VARCHAR(36) NOT NULL, name VARCHAR(160) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(32) NOT NULL, lines JSONB NOT NULL, revision INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE TABLE procurement_receipts (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, order_id VARCHAR(36) NOT NULL, line_id VARCHAR(36) NOT NULL, kind VARCHAR(32) NOT NULL, quantity NUMERIC(24,6) NOT NULL, warehouse_id VARCHAR(36) DEFAULT NULL, unit_cost NUMERIC(24,6) DEFAULT NULL, currency VARCHAR(3) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, inventory_movement_id VARCHAR(36) DEFAULT NULL, equipment_ids JSONB NOT NULL, returned_quantity NUMERIC(24,6) NOT NULL, blocked_reason VARCHAR(160) DEFAULT NULL, revision INT NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE TABLE procurement_operations (organization_id VARCHAR(36) NOT NULL, client_operation_id VARCHAR(36) NOT NULL, kind VARCHAR(32) NOT NULL, fingerprint VARCHAR(64) NOT NULL, receipt_id VARCHAR(36) NOT NULL, declaration JSONB NOT NULL, PRIMARY KEY(organization_id, client_operation_id))');
    $this->addSql("COMMENT ON COLUMN procurement_suppliers.archived_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN procurement_suppliers.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN procurement_suppliers.updated_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN procurement_orders.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN procurement_orders.updated_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN procurement_receipts.received_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN procurement_receipts.created_at IS '(DC2Type:datetime_immutable)'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE procurement_operations');
    $this->addSql('DROP TABLE procurement_receipts');
    $this->addSql('DROP TABLE procurement_orders');
    $this->addSql('DROP TABLE procurement_suppliers');
  }
}
