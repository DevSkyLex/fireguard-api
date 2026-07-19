<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Wave-3 schema lot (L3.0): inert legal-profile and deferred-purge columns on
 * `organizations`, ahead of the wave's feature lots.
 *
 * Adds a legal-profile group (`country`, `legal_type`, `legal_name`,
 * `registration_number`, `vat_number` — activated by L3.1) and a
 * deferred-purge group (`purge_scheduled_at`, `purged_at` — activated by
 * L3.3) to `organizations`, plus an index on `purge_scheduled_at` backing the
 * daily purge sweep.
 *
 * Every column is NULLABLE and every statement strictly ADDITIVE — no
 * existing organization has a legal profile or a purge schedule today, and a
 * legal profile is optional metadata rather than identity — because this lot
 * lands the columns inert ahead of the feature lots that consume them, and
 * must be safe to deploy on its own. `purge_scheduled_at` NULL means "not
 * scheduled for purge"; archiving an organization does NOT, by itself, set
 * it — L3.3 owns that policy. `purged_at` NULL means "not yet purged" and
 * makes the (two-database, no-global-transaction) purge sweep idempotent and
 * resumable once set.
 *
 * Hand-written rather than generated: the `auth` and `main` entity managers
 * share one Postgres schema in development, so `doctrine:migrations:diff`
 * against `main` proposes dropping every `auth` table (`users`, `sessions`,
 * `audit_events`, `otps`, `clients`, `tenants`, …) — see `src/Messaging/MODULE.md`.
 * Only the statements below are intended.
 */
final class Version20260718130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wave-3 schema lot (L3.0): organizations legal-profile columns (country, legal_type, legal_name, registration_number, vat_number) and deferred-purge columns (purge_scheduled_at, purged_at) plus a purge-sweep index.';
    }

    public function up(Schema $schema): void
    {
        // --- Legal profile (scaffolded here, activated by L3.1) --------------------
        $this->addSql('ALTER TABLE organizations ADD country VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD legal_type VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD legal_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD registration_number VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD vat_number VARCHAR(32) DEFAULT NULL');

        // --- Deferred purge (scaffolded here, activated by L3.3) -------------------
        // No FK, no CASCADE: this is a schedule/marker pair on the organization
        // itself, not a relation. The index keeps the daily sweep a bounded scan
        // over the (small) set of organizations that actually have a schedule.
        $this->addSql('ALTER TABLE organizations ADD purge_scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD purged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN organizations.purge_scheduled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN organizations.purged_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_organization_purge_scheduled_at ON organizations (purge_scheduled_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_organization_purge_scheduled_at');
        $this->addSql('ALTER TABLE organizations DROP purged_at');
        $this->addSql('ALTER TABLE organizations DROP purge_scheduled_at');

        $this->addSql('ALTER TABLE organizations DROP vat_number');
        $this->addSql('ALTER TABLE organizations DROP registration_number');
        $this->addSql('ALTER TABLE organizations DROP legal_name');
        $this->addSql('ALTER TABLE organizations DROP legal_type');
        $this->addSql('ALTER TABLE organizations DROP country');
    }
}
