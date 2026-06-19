<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

use function sprintf;

/**
 * Domain Version20260619150000.
 *
 * Corrects the seeded plan rows whose `limits` column still carries the legacy
 * feature-gating arrays (e.g. ["facilities","equipment",...]) left over from the
 * pre-quota seed. Rewrites the three system plans to their quota maps so the
 * plan mapper no longer drops every entry (which made every plan resolve to
 * "unlimited"). Scoped to the known seed identifiers; user-created plans are
 * untouched. Idempotent: re-running re-applies the same canonical values.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260619150000 extends AbstractMigration
{
  private const string FREE_PLAN_ID = '22222222-2222-4222-8222-222222222221';

  private const string PRO_PLAN_ID = '22222222-2222-4222-8222-222222222222';

  private const string ENTERPRISE_PLAN_ID = '22222222-2222-4222-8222-222222222223';

  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the migration description
   */
  public function getDescription(): string
  {
    return 'Backfill seeded plan limits with quota maps (replace legacy feature arrays)';
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
    $limits = [
      self::FREE_PLAN_ID => '{"members":5,"facilities":2,"equipment":50,"inspections":100}',
      self::PRO_PLAN_ID => '{"members":50,"facilities":25,"equipment":2000,"inspections":5000}',
      self::ENTERPRISE_PLAN_ID => '{}',
    ];

    foreach ($limits as $id => $json) {
      $this->addSql(sprintf("UPDATE plans SET limits = '%s' WHERE id = '%s'", $json, $id));
    }

    // Backfill the DC2Type comment the original CREATE TABLE omitted so the
    // plans table matches the rest of the schema (silences a real diff).
    $this->addSql("COMMENT ON COLUMN plans.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN plans.updated_at IS '(DC2Type:datetime_immutable)'");
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
    // Irreversible data correction: the legacy feature arrays are not restored.
  }
}
