<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729150040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add intervention_activities.client_id (offline-outbox idempotency key) with a unique constraint so a replayed comment write cannot be duplicated.';
    }

    public function up(Schema $schema): void
    {
        // Hand-trimmed: the raw diff also proposed dropping ~24 unrelated auth
        // tables (users, roles, sessions, otps, doctrine_migration_versions_auth, ...)
        // that exist in this physical database but are not part of the `main`
        // entity manager's mappings — pre-existing drift, out of scope here. See
        // MODULE.md / migration report for the full note; only the
        // intervention_activities change is applied.
        $this->addSql('ALTER TABLE intervention_activities ADD client_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_intervention_activity_client_id ON intervention_activities (client_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_intervention_activity_client_id');
        $this->addSql('ALTER TABLE intervention_activities DROP client_id');
    }
}
