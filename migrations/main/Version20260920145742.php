<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add durable receipts for atomic Stripe reconciliation in the main database.
 */
final class Version20260920145742 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Journal committed Stripe events by identifier and environment';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE billing_stripe_events (event_id VARCHAR(255) NOT NULL, live_mode BOOLEAN NOT NULL, organization_id VARCHAR(36) NOT NULL, event_type VARCHAR(128) NOT NULL, event_created INT NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(event_id, live_mode))');
    $this->addSql('CREATE INDEX idx_billing_event_org_processed ON billing_stripe_events (organization_id, processed_at)');
    $this->addSql('COMMENT ON COLUMN billing_stripe_events.processed_at IS \'(DC2Type:datetime_immutable)\'');
  }

  public function down(Schema $schema): void
  {
    // Rolling back removes deduplication history; stop webhook delivery first.
    $this->addSql('DROP TABLE billing_stripe_events');
  }
}
