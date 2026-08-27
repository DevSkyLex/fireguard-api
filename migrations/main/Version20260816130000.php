<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Equipment plan Phase 4 — pin equipment on a floor plan. Adds `plan_position`
 * to `equipment`: `{"attachmentId": "<uuid>", "x": float, "y": float}`,
 * normalized 0-1 image coordinates bound to a floor plan attachment.
 * Hand-written `JSONB` rather than a Doctrine-diffed `JSON` column, mirroring
 * `facilities.plan_geometry` (see Version20260816120000): Doctrine's `json`
 * DBAL type (used on `EquipmentRecord::$planPosition`) round-trips through
 * `json_encode`/`json_decode` regardless of the underlying Postgres storage
 * type, so the ORM mapping is unaffected while the physical column gets the
 * indexable, binary-comparable `JSONB` — needed by the plan-overlay read's
 * `plan_position ->> 'attachmentId'` filter.
 */
final class Version20260816130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Equipment plan Phase 4: add plan_position (JSONB, nullable) to equipment.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD plan_position JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment DROP plan_position');
    }
}
