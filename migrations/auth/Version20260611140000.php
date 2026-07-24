<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260611140000.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260611140000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * Executes the get description operation.
   *
   * @since 1.0.0
   *
   * @return string the get description result
   */
  public function getDescription(): string
  {
    return 'Add the Messenger async transport table';
  }

  /**
   * Method up.
   *
   * Executes the up operation.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_messenger_messages_queue_name ON messenger_messages (queue_name)');
    $this->addSql('CREATE INDEX idx_messenger_messages_available_at ON messenger_messages (available_at)');
    $this->addSql('CREATE INDEX idx_messenger_messages_delivered_at ON messenger_messages (delivered_at)');
  }

  /**
   * Method down.
   *
   * Executes the down operation.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE messenger_messages');
  }
}
