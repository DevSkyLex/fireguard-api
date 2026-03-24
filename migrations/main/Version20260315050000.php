<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add description column to organization_roles table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization_roles ADD description TEXT NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_roles DROP COLUMN description');
    }
}
