<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Add durable generation fencing and recoverable delivery leases. */
final class Version20261011120000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Fence webhook generations, lease HTTP attempts and reserve durable wakes.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE webhook_deliveries ADD generation INT DEFAULT 0 NOT NULL, ADD attempt_token VARCHAR(36) DEFAULT NULL, ADD lease_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD wake_token VARCHAR(36) DEFAULT NULL, ADD wake_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD technical_failures INT DEFAULT 0 NOT NULL, ADD quarantined BOOLEAN DEFAULT FALSE NOT NULL');
    $this->addSql("COMMENT ON COLUMN webhook_deliveries.lease_until IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN webhook_deliveries.wake_at IS '(DC2Type:datetime_immutable)'");
  }

  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE webhook_deliveries DROP generation, DROP attempt_token, DROP lease_until, DROP wake_token, DROP wake_at, DROP technical_failures, DROP quarantined');
  }
}
