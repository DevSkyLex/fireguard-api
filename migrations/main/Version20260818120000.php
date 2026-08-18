<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260818120000.
 *
 * Indexes `intervention_recurrence_runs.intervention_id` (main DB). The table
 * was only ever read forwards — recurrence to occurrence, covered by the
 * unique `(recurrence_id, occurrence_date)` constraint. The intervention
 * detail read now walks it backwards to expose the recurrence that
 * materialized an intervention, which without this index is a sequential
 * scan over every run ever recorded.
 *
 * Additive and reversible: no data is moved and no column changes.
 */
final class Version20260818120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index intervention_recurrence_runs.intervention_id for the intervention -> recurrence lookup.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_intervention_recurrence_run_intervention ON intervention_recurrence_runs (intervention_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_intervention_recurrence_run_intervention');
    }
}
