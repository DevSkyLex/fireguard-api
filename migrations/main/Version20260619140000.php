<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

use function json_encode;
use function sprintf;

use const JSON_FORCE_OBJECT;

/**
 * Domain Version20260619140000.
 *
 * Introduces subscription plans: a `plans` catalog table, an organization
 * `plan_id` reference, seeded default plans and a backfill of existing
 * organizations onto the default plan.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260619140000 extends AbstractMigration
{
  private const string DEFAULT_PLAN_ID = '22222222-2222-4222-8222-222222222221';

  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the migration description
   */
  public function getDescription(): string
  {
    return 'Add subscription plans catalog (resource quotas) and organization plan assignment';
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
    $this->addSql('CREATE TABLE plans (id VARCHAR(36) NOT NULL, plan_key VARCHAR(60) NOT NULL, name VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, limits JSON NOT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, sort_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_plan_key ON plans (plan_key)');
    $this->addSql('CREATE INDEX idx_plan_is_default ON plans (is_default)');
    $this->addSql('CREATE INDEX idx_plan_is_active_sort ON plans (is_active, sort_order)');
    $this->addSql("COMMENT ON COLUMN plans.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN plans.updated_at IS '(DC2Type:datetime_immutable)'");

    $this->addSql('ALTER TABLE organizations ADD plan_id VARCHAR(36) DEFAULT NULL');
    $this->addSql('CREATE INDEX idx_organization_plan ON organizations (plan_id)');

    $now = '2026-06-19 12:00:00';
    $plans = [
      [self::DEFAULT_PLAN_ID, 'free', 'Free', 'Get started with the essentials', ['members' => 5, 'facilities' => 2, 'equipment' => 50, 'inspections' => 100], true, 1],
      ['22222222-2222-4222-8222-222222222222', 'pro', 'Pro', 'Room to grow for active teams', ['members' => 50, 'facilities' => 25, 'equipment' => 2000, 'inspections' => 5000], false, 2],
      ['22222222-2222-4222-8222-222222222223', 'max', 'Max', 'Maximum headroom for large organizations', ['members' => 250, 'facilities' => 125, 'equipment' => 10000, 'inspections' => 25000], false, 3],
    ];

    foreach ($plans as [$id, $key, $name, $description, $limits, $isDefault, $sortOrder]) {
      $this->addSql(sprintf(
        "INSERT INTO plans (id, plan_key, name, description, limits, is_active, is_default, sort_order, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', TRUE, %s, %d, '%s', '%s')",
        $id,
        $key,
        $name,
        $description,
        json_encode($limits, JSON_FORCE_OBJECT),
        $isDefault ? 'TRUE' : 'FALSE',
        $sortOrder,
        $now,
        $now,
      ));
    }

    $this->addSql(sprintf("UPDATE organizations SET plan_id = '%s' WHERE plan_id IS NULL", self::DEFAULT_PLAN_ID));
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
    $this->addSql('DROP INDEX idx_organization_plan');
    $this->addSql('ALTER TABLE organizations DROP plan_id');
    $this->addSql('DROP TABLE plans');
  }
}
