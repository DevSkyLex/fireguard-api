<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261006103000
 *
 * Adds operation facts and immutable closure dossiers without rewriting historical interventions.
 *
 * @category Migration
 */
final class Version20261006103000 extends AbstractMigration
{
  /** Method getDescription. */
  public function getDescription(): string
  {
    return 'Add independent preventive work item sources, staged execution facts and versioned closure snapshots.';
  }

  /** Method up. */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE intervention_work_items ADD operation_id VARCHAR(36) DEFAULT NULL, ADD occurrence_id VARCHAR(36) DEFAULT NULL, ADD operation_kind VARCHAR(16) DEFAULT NULL, ADD execution_result JSON DEFAULT NULL');
    $this->addSql('ALTER TABLE interventions ADD closure_snapshot JSON DEFAULT NULL');
  }

  /** Method down. Removing the added facts loses their captured historical contents. */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE intervention_work_items DROP operation_id, DROP occurrence_id, DROP operation_kind, DROP execution_result');
    $this->addSql('ALTER TABLE interventions DROP closure_snapshot');
  }
}
