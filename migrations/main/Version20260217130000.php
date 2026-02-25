<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add legal_type to organization_legal_profile for type-driven onboarding rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization_legal_profile ADD legal_type VARCHAR(32) DEFAULT 'other' NOT NULL");
        $this->addSql("ALTER TABLE organization_legal_profile ADD CONSTRAINT chk_organization_legal_profile_legal_type CHECK (legal_type IN ('company', 'non_profit', 'public_sector', 'individual', 'other'))");
        $this->addSql('ALTER TABLE organization_legal_profile ALTER legal_type DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_legal_profile DROP CONSTRAINT IF EXISTS chk_organization_legal_profile_legal_type');
        $this->addSql('ALTER TABLE organization_legal_profile DROP legal_type');
    }
}
