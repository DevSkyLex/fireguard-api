<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260716205604.
 *
 * Introduces organization-scoped intervention templates (`intervention_templates`,
 * unique per `(organization_id, name)`) and their ordered planned items
 * (`intervention_template_items`, cascade-deleted with the template),
 * backing the intervention templates slice (Lot 3, without recurrence).
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260716205604 extends AbstractMigration
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
    return 'Add intervention_templates and intervention_template_items tables';
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
      'CREATE TABLE intervention_templates ('
      . 'id VARCHAR(36) NOT NULL, '
      . 'organization_id VARCHAR(36) NOT NULL, '
      . 'name VARCHAR(160) NOT NULL, '
      . 'description TEXT DEFAULT NULL, '
      . 'intervention_type VARCHAR(32) NOT NULL, '
      . 'default_priority VARCHAR(16) NOT NULL, '
      . 'default_site_id VARCHAR(36) DEFAULT NULL, '
      . 'default_responsible_id VARCHAR(36) DEFAULT NULL, '
      . 'duration VARCHAR(32) DEFAULT NULL, '
      . 'label_ids JSON NOT NULL, '
      . 'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'PRIMARY KEY(id))',
    );
    $this->addSql(
      'CREATE INDEX idx_intervention_template_organization ON intervention_templates (organization_id)',
    );
    $this->addSql(
      'ALTER TABLE intervention_templates ADD CONSTRAINT uniq_intervention_template_org_name UNIQUE (organization_id, name)',
    );
    $this->addSql(
      'ALTER TABLE intervention_templates ADD CONSTRAINT FK_intervention_template_organization '
      . 'FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
    );
    $this->addSql(
      'COMMENT ON COLUMN intervention_templates.created_at IS \'(DC2Type:datetime_immutable)\'',
    );
    $this->addSql(
      'COMMENT ON COLUMN intervention_templates.updated_at IS \'(DC2Type:datetime_immutable)\'',
    );

    $this->addSql(
      'CREATE TABLE intervention_template_items ('
      . 'id VARCHAR(36) NOT NULL, '
      . 'template_id VARCHAR(36) NOT NULL, '
      . 'position INT NOT NULL, '
      . 'action VARCHAR(60) NOT NULL, '
      . 'target TEXT DEFAULT NULL, '
      . 'result_resource VARCHAR(255) DEFAULT NULL, '
      . 'required BOOLEAN NOT NULL, '
      . 'default_assignee_id VARCHAR(36) DEFAULT NULL, '
      . 'PRIMARY KEY(id))',
    );
    $this->addSql(
      'CREATE INDEX idx_intervention_template_item_template ON intervention_template_items (template_id)',
    );
    $this->addSql(
      'ALTER TABLE intervention_template_items ADD CONSTRAINT FK_intervention_template_item_template '
      . 'FOREIGN KEY (template_id) REFERENCES intervention_templates (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
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

    $this->addSql('DROP TABLE intervention_template_items');
    $this->addSql('DROP TABLE intervention_templates');
  }
}
