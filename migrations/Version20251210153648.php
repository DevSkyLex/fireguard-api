<?php

declare(strict_types=1);

namespace DoctrineMigrations;

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
        $this->addSql('DROP INDEX unique_assignment');
        $this->addSql('ALTER TABLE role_assignments DROP company_id');
        $this->addSql('CREATE UNIQUE INDEX unique_assignment ON role_assignments (role_id, subject_type, subject_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX unique_assignment');
        $this->addSql('ALTER TABLE role_assignments ADD company_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_assignment ON role_assignments (role_id, subject_type, subject_id, company_id)');
    }
}
