<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for OTP (One-Time Password) table.
 */
final class Version20251206170000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Create otps table for OTP verification';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE otps (
            id UUID NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            purpose VARCHAR(50) NOT NULL,
            channel VARCHAR(20) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            attempts INT DEFAULT 0 NOT NULL,
            max_attempts INT NOT NULL,
            verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
    $this->addSql('CREATE INDEX idx_otp_user_purpose ON otps (user_id, purpose)');
    $this->addSql('CREATE INDEX idx_otp_expires ON otps (expires_at)');
    $this->addSql('COMMENT ON COLUMN otps.expires_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN otps.verified_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN otps.created_at IS \'(DC2Type:datetime_immutable)\'');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE otps');
  }
}
