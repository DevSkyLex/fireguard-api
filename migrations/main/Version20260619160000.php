<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260619160000.
 *
 * Rebrands the third seeded plan from "Enterprise" (unlimited) to "Max" with
 * concrete quotas set to 5x the Pro plan. Scoped to the known seed identifier;
 * user-created plans are untouched. Idempotent.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260619160000 extends AbstractMigration
{
  private const string MAX_PLAN_ID = '22222222-2222-4222-8222-222222222223';

  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the migration description
   */
  public function getDescription(): string
  {
    return 'Rename the Enterprise plan to Max with 5x Pro quotas';
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
    $this->addSql(
      "UPDATE plans SET plan_key = 'max', name = 'Max', description = 'Maximum headroom for large organizations', limits = '{\"members\":250,\"facilities\":125,\"equipment\":10000,\"inspections\":25000}' WHERE id = '" . self::MAX_PLAN_ID . "'",
    );
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
    $this->addSql(
      "UPDATE plans SET plan_key = 'enterprise', name = 'Enterprise', description = 'Unlimited usage for large organizations', limits = '{}' WHERE id = '" . self::MAX_PLAN_ID . "'",
    );
  }
}
