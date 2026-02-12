<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notifications table for generic notification module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notifications (id VARCHAR(36) NOT NULL, recipient_user_id VARCHAR(36) DEFAULT NULL, recipient_email VARCHAR(320) DEFAULT NULL, type VARCHAR(120) NOT NULL, subject VARCHAR(255) NOT NULL, body TEXT NOT NULL, payload JSON NOT NULL, channels JSON NOT NULL, is_read BOOLEAN NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_notifications_recipient_user_id ON notifications (recipient_user_id)');
        $this->addSql('CREATE INDEX idx_notifications_recipient_email ON notifications (recipient_email)');
        $this->addSql('CREATE INDEX idx_notifications_created_at ON notifications (created_at)');
        $this->addSql('CREATE INDEX idx_notifications_user_read ON notifications (recipient_user_id, is_read)');
        $this->addSql("COMMENT ON COLUMN notifications.read_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN notifications.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN notifications.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifications');
    }
}
