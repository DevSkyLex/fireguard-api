<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align authorization table constraint and index names with current Doctrine mapping';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('ALTER TABLE role_assignments RENAME CONSTRAINT fk_role_assignments_role TO FK_2DD0854D60322AC');
        $this->addSql('DROP INDEX idx_roles_name');
        $this->addSql('ALTER INDEX idx_role_permissions_role RENAME TO IDX_1FBA94E6D60322AC');
        $this->addSql('ALTER INDEX idx_role_permissions_permission RENAME TO IDX_1FBA94E6FED90CCA');
        $this->addSql('ALTER TABLE role_permissions RENAME CONSTRAINT fk_role_permissions_role TO FK_1FBA94E6D60322AC');
        $this->addSql('ALTER TABLE role_permissions RENAME CONSTRAINT fk_role_permissions_permission TO FK_1FBA94E6FED90CCA');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'This migration is intended for PostgreSQL only.',
        );

        $this->addSql('ALTER TABLE role_assignments RENAME CONSTRAINT FK_2DD0854D60322AC TO fk_role_assignments_role');
        $this->addSql('CREATE INDEX idx_roles_name ON roles (name)');
        $this->addSql('ALTER INDEX IDX_1FBA94E6D60322AC RENAME TO idx_role_permissions_role');
        $this->addSql('ALTER INDEX IDX_1FBA94E6FED90CCA RENAME TO idx_role_permissions_permission');
        $this->addSql('ALTER TABLE role_permissions RENAME CONSTRAINT FK_1FBA94E6D60322AC TO fk_role_permissions_role');
        $this->addSql('ALTER TABLE role_permissions RENAME CONSTRAINT FK_1FBA94E6FED90CCA TO fk_role_permissions_permission');
    }
}
