<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260620120000.
 *
 * Introduces the Billing module's subscription projection: a
 * `billing_subscriptions` table linking each organization to its Stripe customer
 * and subscription, kept in sync by Stripe webhooks.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260620120000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the migration description
   */
  public function getDescription(): string
  {
    return 'Add billing_subscriptions table (Stripe subscription projection per organization)';
  }

  /**
   * Method up.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE billing_subscriptions (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, stripe_customer_id VARCHAR(255) NOT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, status VARCHAR(40) NOT NULL, plan_key VARCHAR(60) DEFAULT NULL, billing_interval VARCHAR(10) DEFAULT NULL, current_period_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancel_at_period_end BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_billing_subscription_org ON billing_subscriptions (organization_id)');
    $this->addSql('CREATE INDEX idx_billing_subscription_customer ON billing_subscriptions (stripe_customer_id)');
    $this->addSql('CREATE INDEX idx_billing_subscription_stripe ON billing_subscriptions (stripe_subscription_id)');
    $this->addSql("COMMENT ON COLUMN billing_subscriptions.current_period_end IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN billing_subscriptions.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN billing_subscriptions.updated_at IS '(DC2Type:datetime_immutable)'");
  }

  /**
   * Method down.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE billing_subscriptions');
  }
}
