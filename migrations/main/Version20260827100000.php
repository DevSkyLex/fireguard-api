<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260827100000.
 *
 * Adds the SLA escalation anti-duplicate stamp to non-conformities
 * (`sla_breach_notified_at`): set once the hourly Inspection sweep has
 * signalled the breach to the organization's administrators, cleared when a
 * resolved non-conformity is reopened so a still-breached one is escalated
 * again. Mirrors Version20260813092835 (the intervention reminder stamps).
 */
final class Version20260827100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the non-conformity SLA breach notification stamp backing the hourly escalation sweep.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE non_conformities ADD sla_breach_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN non_conformities.sla_breach_notified_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE non_conformities DROP sla_breach_notified_at');
    }
}
