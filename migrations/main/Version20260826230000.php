<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Version20260826230000.
 *
 * Drops four column defaults that were backfill scaffolding, never contract.
 *
 * `ADD COLUMN ... NOT NULL` is rejected on a non-empty table without a
 * `DEFAULT`, so Version20260813140000, Version20260816110904 and
 * Version20260816170000 each supplied one to get the column in. The default
 * had done its job the moment the statement committed. Doctrine's mapping
 * never declared it, so `doctrine:schema:validate --em=main` reported the
 * database out of sync with the mapping — which is what the CI job
 * "Prepare PostgreSQL test environment" failed on, for every test job at
 * once, on the first PR to `main` in five weeks.
 *
 * Dropping the default rather than declaring it in the mapping is deliberate.
 * Every one of these columns is a non-nullable typed property that Doctrine
 * always writes, so the default can never be reached by the application. What
 * it can do is absorb a bug: an INSERT that forgets `kind` would silently
 * store 'document' instead of failing. The mapping is the contract of record;
 * the database should not quietly disagree with it.
 *
 * No data is read or moved. `down()` restores the exact literals the three
 * original migrations used.
 */
final class Version20260826230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the four backfill column defaults that put main out of sync with its mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facility_attachments ALTER kind DROP DEFAULT');
        $this->addSql('ALTER TABLE facility_attachments ALTER is_primary_plan DROP DEFAULT');
        $this->addSql('ALTER TABLE intervention_attachments ALTER kind DROP DEFAULT');
        $this->addSql('ALTER TABLE import_jobs ALTER is_dry_run DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE facility_attachments ALTER kind SET DEFAULT 'document'");
        $this->addSql('ALTER TABLE facility_attachments ALTER is_primary_plan SET DEFAULT false');
        $this->addSql("ALTER TABLE intervention_attachments ALTER kind SET DEFAULT 'file'");
        $this->addSql('ALTER TABLE import_jobs ALTER is_dry_run SET DEFAULT false');
    }
}
