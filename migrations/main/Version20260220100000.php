<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add skipped_steps and step_history columns to organization_onboarding_sessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization_onboarding_sessions ADD COLUMN skipped_steps JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE organization_onboarding_sessions ADD COLUMN step_history JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_onboarding_sessions DROP COLUMN IF EXISTS step_history');
        $this->addSql('ALTER TABLE organization_onboarding_sessions DROP COLUMN IF EXISTS skipped_steps');
    }
}
