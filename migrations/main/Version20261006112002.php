<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Additive physical return declarations preserve evidence during stock reconciliation. */
final class Version20261006112002 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Retain physical supply returns awaiting inventory reconciliation.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE procurement_receipts ADD pending_return_quantity NUMERIC(24,6) DEFAULT 0.000000 NOT NULL');
    $this->addSql('CREATE TABLE procurement_returns (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, receipt_id VARCHAR(36) NOT NULL, client_operation_id VARCHAR(36) NOT NULL, quantity NUMERIC(24,6) NOT NULL, reason TEXT NOT NULL, actor_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(32) NOT NULL, inventory_movement_id VARCHAR(36) DEFAULT NULL, blocked_reason VARCHAR(160) DEFAULT NULL, reconciled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revision INT NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_procurement_return_org_receipt ON procurement_returns (organization_id, receipt_id)');
    $this->addSql("COMMENT ON COLUMN procurement_returns.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN procurement_returns.reconciled_at IS '(DC2Type:datetime_immutable)'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE procurement_returns');
    $this->addSql('ALTER TABLE procurement_receipts DROP pending_return_quantity');
  }
}
