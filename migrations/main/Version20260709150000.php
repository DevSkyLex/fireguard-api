<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260709150000.
 *
 * Introduces organization-scoped intervention labels (`intervention_labels`,
 * unique per `(organization_id, name)`) and the intervention/label
 * many-to-many assignment table (`intervention_label_assignments`, owning
 * side on `interventions`), backing Phase B3.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260709150000 extends AbstractMigration
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
    return 'Add intervention_labels and intervention_label_assignments tables';
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
      'CREATE TABLE intervention_labels ('
      . 'id VARCHAR(36) NOT NULL, '
      . 'organization_id VARCHAR(36) NOT NULL, '
      . 'name VARCHAR(50) NOT NULL, '
      . 'color VARCHAR(7) NOT NULL, '
      . 'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
      . 'PRIMARY KEY(id))',
    );
    $this->addSql(
      'ALTER TABLE intervention_labels ADD CONSTRAINT uniq_intervention_label_org_name UNIQUE (organization_id, name)',
    );
    $this->addSql(
      'ALTER TABLE intervention_labels ADD CONSTRAINT FK_intervention_label_organization '
      . 'FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
    );

    $this->addSql(
      'CREATE TABLE intervention_label_assignments ('
      . 'intervention_id VARCHAR(36) NOT NULL, '
      . 'label_id VARCHAR(36) NOT NULL, '
      . 'PRIMARY KEY(intervention_id, label_id))',
    );
    $this->addSql(
      'CREATE INDEX IDX_9181CB168EAE3863 ON intervention_label_assignments (intervention_id)',
    );
    $this->addSql(
      'CREATE INDEX IDX_9181CB1633B92F39 ON intervention_label_assignments (label_id)',
    );
    $this->addSql(
      'ALTER TABLE intervention_label_assignments ADD CONSTRAINT FK_intervention_label_assignment_intervention '
      . 'FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
    );
    $this->addSql(
      'ALTER TABLE intervention_label_assignments ADD CONSTRAINT FK_intervention_label_assignment_label '
      . 'FOREIGN KEY (label_id) REFERENCES intervention_labels (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
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

    $this->addSql('DROP TABLE intervention_label_assignments');
    $this->addSql('DROP TABLE intervention_labels');
  }
}
