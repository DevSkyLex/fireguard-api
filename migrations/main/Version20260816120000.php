<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Facility plan Phase 4 — spatial zone geometry. Adds `plan_geometry` to
 * `facilities`: `{"attachmentId": "<uuid>", "points": [[x, y], ...]}`,
 * normalized 0-1 image coordinates bound to an ancestor's floor plan
 * attachment. Hand-written `JSONB` rather than a Doctrine-diffed `JSON`
 * column: Doctrine's `json` DBAL type (used on `FacilityRecord::$planGeometry`,
 * matching the existing `metadata` column) round-trips through
 * `json_encode`/`json_decode` regardless of the underlying Postgres storage
 * type, so the ORM mapping is unaffected while the physical column gets the
 * indexable, binary-comparable `JSONB` — needed by the plan-overlay read's
 * `plan_geometry ->> 'attachmentId'` filter.
 */
final class Version20260816120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facility plan Phase 4: add plan_geometry (JSONB, nullable) to facilities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facilities ADD plan_geometry JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facilities DROP plan_geometry');
    }
}
