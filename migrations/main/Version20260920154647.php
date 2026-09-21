<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920154647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add main-owned event consumption receipts for durable workflow delivery';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE consumed_events (id VARCHAR(64) NOT NULL, consumed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN consumed_events.consumed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Stop delivery before rollback: dropping receipts loses deduplication history.
        $this->addSql('DROP TABLE consumed_events');
    }
}
