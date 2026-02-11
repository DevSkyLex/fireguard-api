<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210153648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IF EXISTS unique_assignment');
        $this->addSql('ALTER TABLE role_assignments DROP COLUMN IF EXISTS organization_id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_assignment ON role_assignments (role_id, subject_type, subject_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA IF NOT EXISTS public');
        $this->addSql('DROP INDEX IF EXISTS unique_assignment');
        $this->addSql('ALTER TABLE role_assignments ADD COLUMN IF NOT EXISTS organization_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_assignment ON role_assignments (role_id, subject_type, subject_id, organization_id)');
    }
}


