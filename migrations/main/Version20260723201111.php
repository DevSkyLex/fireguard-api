<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723201111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resync main schema with the mappings: drop stale cross-module FKs (now soft references), align auto-generated index/constraint names, and normalize immutable-datetime column comments. Defaults and partial indexes are declared in the mappings/listener, not dropped. No table or column is dropped.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT fk_equipment_intervention');
        $this->addSql('ALTER INDEX uniq_equipment_client_id RENAME TO UNIQ_D338D58319EB6921');
        $this->addSql('ALTER TABLE facilities DROP CONSTRAINT fk_facility_intervention');
        $this->addSql('ALTER INDEX uniq_facility_client_id RENAME TO UNIQ_ADE885D519EB6921');
        $this->addSql('ALTER TABLE import_jobs DROP CONSTRAINT fk_import_jobs_organization_id');
        $this->addSql('ALTER TABLE inspection_responses DROP CONSTRAINT fk_response_inspection');
        $this->addSql('ALTER TABLE inspection_responses DROP CONSTRAINT fk_response_intervention');
        $this->addSql('ALTER TABLE inspection_responses ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE inspection_responses ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN inspection_responses.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN inspection_responses.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX uniq_inspection_response_client_id RENAME TO UNIQ_CD1506CC19EB6921');
        $this->addSql('ALTER TABLE inspections DROP CONSTRAINT fk_inspection_intervention');
        $this->addSql('ALTER INDEX uniq_inspection_client_id RENAME TO UNIQ_8625499019EB6921');
        $this->addSql('ALTER TABLE intervention_activities ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_activities.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE intervention_attachments DROP CONSTRAINT fk_39377b11fa6e1a26');
        $this->addSql('DROP INDEX idx_intervention_attachment_work_item');
        $this->addSql('ALTER TABLE intervention_changes ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_changes ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_changes.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN intervention_changes.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE intervention_labels ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_labels ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_labels.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN intervention_labels.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE intervention_publications ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_publications ALTER completed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_publications.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN intervention_publications.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_intervention_recurrence_run_recurrence RENAME TO IDX_83DB7CDA2C414CE8');
        $this->addSql('ALTER INDEX idx_intervention_recurrence_template RENAME TO IDX_298BE9605DA0FB8');
        $this->addSql('ALTER TABLE intervention_work_items ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_work_items ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_work_items.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN intervention_work_items.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE interventions ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE interventions ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE interventions ALTER planned_start_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE interventions ALTER due_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN interventions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN interventions.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN interventions.planned_start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN interventions.due_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook_deliveries DROP CONSTRAINT fk_webhook_deliveries_organization_id');
        $this->addSql('ALTER TABLE webhook_deliveries DROP CONSTRAINT fk_webhook_deliveries_subscription_id');
        $this->addSql('ALTER TABLE webhook_subscriptions DROP CONSTRAINT fk_webhook_subscriptions_organization_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention_publications ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_publications ALTER completed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_publications.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN intervention_publications.completed_at IS NULL');
        $this->addSql('ALTER TABLE webhook_subscriptions ADD CONSTRAINT fk_webhook_subscriptions_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT fk_equipment_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_d338d58319eb6921 RENAME TO uniq_equipment_client_id');
        $this->addSql('ALTER TABLE intervention_labels ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_labels ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_labels.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN intervention_labels.updated_at IS NULL');
        $this->addSql('ALTER TABLE webhook_deliveries ADD CONSTRAINT fk_webhook_deliveries_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE webhook_deliveries ADD CONSTRAINT fk_webhook_deliveries_subscription_id FOREIGN KEY (subscription_id) REFERENCES webhook_subscriptions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_83db7cda2c414ce8 RENAME TO idx_intervention_recurrence_run_recurrence');
        $this->addSql('ALTER TABLE intervention_work_items ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_work_items ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_work_items.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN intervention_work_items.updated_at IS NULL');
        $this->addSql('ALTER TABLE interventions ALTER planned_start_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE interventions ALTER due_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE interventions ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE interventions ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN interventions.planned_start_at IS NULL');
        $this->addSql('COMMENT ON COLUMN interventions.due_at IS NULL');
        $this->addSql('COMMENT ON COLUMN interventions.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN interventions.updated_at IS NULL');
        $this->addSql('ALTER TABLE facilities ADD CONSTRAINT fk_facility_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_ade885d519eb6921 RENAME TO uniq_facility_client_id');
        $this->addSql('ALTER TABLE intervention_activities ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_activities.created_at IS NULL');
        $this->addSql('ALTER TABLE intervention_attachments ADD CONSTRAINT fk_39377b11fa6e1a26 FOREIGN KEY (work_item_id) REFERENCES intervention_work_items (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_intervention_attachment_work_item ON intervention_attachments (work_item_id)');
        $this->addSql('ALTER TABLE inspections ADD CONSTRAINT fk_inspection_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_8625499019eb6921 RENAME TO uniq_inspection_client_id');
        $this->addSql('ALTER TABLE import_jobs ADD CONSTRAINT fk_import_jobs_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE intervention_changes ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE intervention_changes ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN intervention_changes.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN intervention_changes.updated_at IS NULL');
        $this->addSql('ALTER TABLE inspection_responses ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE inspection_responses ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN inspection_responses.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN inspection_responses.updated_at IS NULL');
        $this->addSql('ALTER TABLE inspection_responses ADD CONSTRAINT fk_response_inspection FOREIGN KEY (inspection_id) REFERENCES inspections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inspection_responses ADD CONSTRAINT fk_response_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_cd1506cc19eb6921 RENAME TO uniq_inspection_response_client_id');
        $this->addSql('ALTER INDEX idx_298be9605da0fb8 RENAME TO idx_intervention_recurrence_template');
    }
}
