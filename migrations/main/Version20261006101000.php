<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
/** Class Version20261006101000. Adds internal customers and optional root-site ownership. @category Migration */
final class Version20261006101000 extends AbstractMigration
{
  public function getDescription(): string { return 'Add organization-owned internal customers and root facility customer reference'; }
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE customers (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, name VARCHAR(160) NOT NULL, code VARCHAR(80) DEFAULT NULL, email VARCHAR(254) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, contacts JSON NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revision INT DEFAULT 1 NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_customer_organization ON customers (organization_id)');
    $this->addSql('CREATE UNIQUE INDEX uniq_customer_organization_code ON customers (organization_id, code)');
    $this->addSql('ALTER TABLE facilities ADD customer_id VARCHAR(36) DEFAULT NULL');
    $this->addSql('CREATE INDEX idx_facility_customer ON facilities (customer_id)');
    $this->addSql("ALTER TABLE facilities ADD CONSTRAINT chk_facility_customer_root CHECK (customer_id IS NULL OR (type = 'site' AND parent_facility_id IS NULL))");
  }
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE facilities DROP CONSTRAINT chk_facility_customer_root');
    $this->addSql('DROP INDEX idx_facility_customer');
    $this->addSql('ALTER TABLE facilities DROP customer_id');
    $this->addSql('DROP TABLE customers');
  }
}
