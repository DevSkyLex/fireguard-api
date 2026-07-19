<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates `webhook_subscriptions` and `webhook_deliveries` (R16 — outbound
 * webhooks). Both tables use plain `organization_id` / `subscription_id`
 * columns (no ORM association), mirroring `import_jobs`'s precedent: the
 * foreign keys are added directly here rather than through a Doctrine
 * mapping. `webhook_deliveries.subscription_id` cascades on delete so
 * removing a subscription also drops its delivery log.
 */
final class Version20260718063344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create webhook_subscriptions and webhook_deliveries (R16 — outbound webhooks).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE webhook_subscriptions (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, url TEXT NOT NULL, secret_ciphertext TEXT NOT NULL, event_types JSON NOT NULL, is_active BOOLEAN NOT NULL, description TEXT DEFAULT \'\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_webhook_subscription_org_active ON webhook_subscriptions (organization_id, is_active)');
        $this->addSql('COMMENT ON COLUMN webhook_subscriptions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN webhook_subscriptions.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook_subscriptions ADD CONSTRAINT fk_webhook_subscriptions_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE webhook_deliveries (id VARCHAR(36) NOT NULL, subscription_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, event_type VARCHAR(64) NOT NULL, event_id VARCHAR(36) NOT NULL, payload JSON NOT NULL, status VARCHAR(16) NOT NULL, http_status INT DEFAULT NULL, attempts INT NOT NULL, last_error TEXT DEFAULT NULL, next_retry_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_webhook_delivery_subscription_event ON webhook_deliveries (subscription_id, event_id)');
        $this->addSql('CREATE INDEX idx_webhook_delivery_status_retry ON webhook_deliveries (status, next_retry_at)');
        $this->addSql('CREATE INDEX idx_webhook_delivery_subscription_created ON webhook_deliveries (subscription_id, created_at)');
        $this->addSql('COMMENT ON COLUMN webhook_deliveries.next_retry_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN webhook_deliveries.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN webhook_deliveries.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN webhook_deliveries.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook_deliveries ADD CONSTRAINT fk_webhook_deliveries_subscription_id FOREIGN KEY (subscription_id) REFERENCES webhook_subscriptions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE webhook_deliveries ADD CONSTRAINT fk_webhook_deliveries_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_deliveries DROP CONSTRAINT fk_webhook_deliveries_subscription_id');
        $this->addSql('ALTER TABLE webhook_deliveries DROP CONSTRAINT fk_webhook_deliveries_organization_id');
        $this->addSql('DROP TABLE webhook_deliveries');

        $this->addSql('ALTER TABLE webhook_subscriptions DROP CONSTRAINT fk_webhook_subscriptions_organization_id');
        $this->addSql('DROP TABLE webhook_subscriptions');
    }
}
