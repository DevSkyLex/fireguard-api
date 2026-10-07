<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Add scoped list indexes without modifying retained procurement evidence. */
final class Version20261006112001 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Index organization-scoped procurement lists and order receipts.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE INDEX idx_procurement_supplier_org_archive ON procurement_suppliers (organization_id, archived_at)');
    $this->addSql('CREATE INDEX idx_procurement_order_org_supplier_status ON procurement_orders (organization_id, supplier_id, status)');
    $this->addSql('CREATE INDEX idx_procurement_receipt_org_order ON procurement_receipts (organization_id, order_id)');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP INDEX idx_procurement_receipt_org_order');
    $this->addSql('DROP INDEX idx_procurement_order_org_supplier_status');
    $this->addSql('DROP INDEX idx_procurement_supplier_org_archive');
  }
}
