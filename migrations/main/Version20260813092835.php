<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the due-date reminder anti-spam stamps to `interventions`
 * (`due_soon_notified_at`, `overdue_notified_at`): set once the
 * corresponding reminder has been sent for the current `dueAt`, reset to
 * `null` whenever `dueAt` is rescheduled.
 */
final class Version20260813092835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add due-date reminder anti-spam stamps to interventions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interventions ADD due_soon_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE interventions ADD overdue_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN interventions.due_soon_notified_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN interventions.overdue_notified_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interventions DROP due_soon_notified_at');
        $this->addSql('ALTER TABLE interventions DROP overdue_notified_at');
    }
}
