<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add country_code to organization_legal_profile for country-specific legal rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization_legal_profile ADD country_code VARCHAR(2) DEFAULT 'FR' NOT NULL");
        $this->addSql("UPDATE organization_legal_profile SET country_code = 'FR' WHERE country_code IS NULL OR TRIM(country_code) = ''");
        $this->addSql("ALTER TABLE organization_legal_profile ADD CONSTRAINT chk_organization_legal_profile_country_code CHECK (country_code ~ '^[A-Z]{2}$')");
        $this->addSql('ALTER TABLE organization_legal_profile ALTER country_code DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_legal_profile DROP CONSTRAINT IF EXISTS chk_organization_legal_profile_country_code');
        $this->addSql('ALTER TABLE organization_legal_profile DROP country_code');
    }
}
