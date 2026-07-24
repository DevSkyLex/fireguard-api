<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260709140000.
 *
 * Introduces the `intervention_activities` table backing the intervention
 * comment and activity feed: member-authored comments plus system-recorded
 * lifecycle events (creation, status transitions), scoped by intervention and
 * denormalized organization id.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260709140000 extends AbstractMigration
{
  /**
   * Method getDescription.
   *
   * @since 1.0.0
   *
   * @return string the get description result
   */
  public function getDescription(): string
  {
    return 'Add intervention_activities table for comments and system activity feed';
  }

  /**
   * Method up.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function up(Schema $schema): void
  {
    $this->abortIf(
      'postgresql' !== $this->connection->getDatabasePlatform()->getName(),
      'This migration is intended for PostgreSQL only.',
    );

    $this->addSql(
      'CREATE TABLE intervention_activities ('
      . 'id VARCHAR(36) NOT NULL, '
      . 'intervention_id VARCHAR(36) NOT NULL, '
      . 'organization_id VARCHAR(36) NOT NULL, '
      . 'actor_id VARCHAR(36) DEFAULT NULL, '
      . 'kind VARCHAR(16) NOT NULL, '
      . 'event VARCHAR(32) NOT NULL, '
      . 'body TEXT DEFAULT NULL, '
      . 'payload JSON DEFAULT NULL, '
      . 'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'PRIMARY KEY(id))',
    );
    $this->addSql(
      'CREATE INDEX idx_intervention_activity_intervention_created '
      . 'ON intervention_activities (intervention_id, created_at)',
    );
    $this->addSql(
      'ALTER TABLE intervention_activities ADD CONSTRAINT FK_intervention_activity_intervention '
      . 'FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
    );
  }

  /**
   * Method down.
   *
   * @since 1.0.0
   *
   * @param Schema $schema the schema value
   */
  public function down(Schema $schema): void
  {
    $this->abortIf(
      'postgresql' !== $this->connection->getDatabasePlatform()->getName(),
      'This migration is intended for PostgreSQL only.',
    );

    $this->addSql('DROP TABLE intervention_activities');
  }
}
