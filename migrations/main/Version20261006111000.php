<?php
declare(strict_types=1);
namespace DoctrineMigrations\Main;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
/** Nonnegative quantitative inventory and immutable physical declaration receipts. @category Migration */
final class Version20261006111000 extends AbstractMigration {
  public function getDescription():string {return 'Add scoped parts, warehouses, nonnegative balances, stock movements and declaration receipts.';}
  public function up(Schema $schema):void {
    $this->addSql('CREATE TABLE inventory_parts (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, label VARCHAR(255) NOT NULL, unit VARCHAR(32) NOT NULL, kind VARCHAR(16) NOT NULL, archived BOOLEAN NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_inventory_parts_code ON inventory_parts (organization_id,code)');
    $this->addSql('CREATE TABLE inventory_warehouses (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, label VARCHAR(255) NOT NULL, archived BOOLEAN NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_inventory_warehouses_code ON inventory_warehouses (organization_id,code)');
    $this->addSql('CREATE TABLE inventory_balances (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, warehouse_id VARCHAR(36) NOT NULL, part_id VARCHAR(36) NOT NULL, quantity NUMERIC(24,6) NOT NULL, total_value NUMERIC(24,6), currency VARCHAR(3) NOT NULL, PRIMARY KEY(id), CONSTRAINT inventory_balance_nonnegative CHECK (quantity >= 0 AND (total_value IS NULL OR total_value >= 0)))');
    $this->addSql('CREATE UNIQUE INDEX uniq_inventory_balances_scope ON inventory_balances (organization_id,warehouse_id,part_id)');
    $this->addSql('CREATE TABLE inventory_movements (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, warehouse_id VARCHAR(36) NOT NULL, part_id VARCHAR(36) NOT NULL, kind VARCHAR(32) NOT NULL, quantity NUMERIC(24,6) NOT NULL, unit_cost NUMERIC(24,6), total_value NUMERIC(24,6), currency VARCHAR(3) NOT NULL, reason TEXT NOT NULL, actor_id VARCHAR(36) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, intervention_id VARCHAR(36), work_item_id VARCHAR(36), equipment_id VARCHAR(36), correction_of VARCHAR(36), source_receipt_id VARCHAR(36), late BOOLEAN NOT NULL, PRIMARY KEY(id))');
    $this->addSql("COMMENT ON COLUMN inventory_movements.occurred_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql('CREATE INDEX idx_inventory_movements_intervention ON inventory_movements (organization_id,intervention_id)');
    $this->addSql('CREATE INDEX idx_inventory_movements_correction ON inventory_movements (organization_id,correction_of)');
    $this->addSql('CREATE TABLE inventory_declarations (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, warehouse_id VARCHAR(36) NOT NULL, part_id VARCHAR(36) NOT NULL, quantity NUMERIC(24,6) NOT NULL, intervention_id VARCHAR(36) NOT NULL, work_item_id VARCHAR(36), equipment_id VARCHAR(36), actor_id VARCHAR(36) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(32) NOT NULL, reason VARCHAR(64), movement_id VARCHAR(36), late BOOLEAN NOT NULL, PRIMARY KEY(id))');
    $this->addSql("COMMENT ON COLUMN inventory_declarations.occurred_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql('CREATE INDEX idx_inventory_declarations_pending ON inventory_declarations (organization_id,intervention_id,status)');
    $this->addSql('CREATE TABLE inventory_operation_receipts (organization_id VARCHAR(36) NOT NULL, client_operation_id VARCHAR(36) NOT NULL, payload_hash VARCHAR(64) NOT NULL, response JSON NOT NULL, PRIMARY KEY(organization_id,client_operation_id))');
  }
  public function down(Schema $schema):void {
    $this->addSql('DROP TABLE inventory_operation_receipts');
    $this->addSql('DROP TABLE inventory_declarations');
    $this->addSql('DROP TABLE inventory_movements');
    $this->addSql('DROP TABLE inventory_balances');
    $this->addSql('DROP TABLE inventory_warehouses');
    $this->addSql('DROP TABLE inventory_parts');
  }
}
