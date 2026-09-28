<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add durable browser delivery positions to existing messaging read markers.
 */
final class Version20260927194418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messaging: record the last message another browser confirmed receiving.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messaging_read_markers ADD last_delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE messaging_read_markers ADD last_delivered_message_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN messaging_read_markers.last_delivered_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messaging_read_markers DROP last_delivered_at');
        $this->addSql('ALTER TABLE messaging_read_markers DROP last_delivered_message_id');
    }
}
