<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing foreign keys for equipment and inspection persistence graphs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D58332C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE equipment_tag_catalog ADD CONSTRAINT FK_485FEBCA32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE checklists ADD CONSTRAINT FK_B0839B3F32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inspections ADD CONSTRAINT FK_8625499032C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE equipment_tag ADD CONSTRAINT FK_32097FE2517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE equipment_tag ADD CONSTRAINT FK_32097FE2BAD26311 FOREIGN KEY (tag_id) REFERENCES equipment_tag_catalog (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE equipment_attachments ADD CONSTRAINT FK_26860EF1517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE checklist_items ADD CONSTRAINT FK_DFF66E93B16D08A7 FOREIGN KEY (checklist_id) REFERENCES checklists (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE non_conformities ADD CONSTRAINT FK_D66C9CACF02F2DDF FOREIGN KEY (inspection_id) REFERENCES inspections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE non_conformities DROP CONSTRAINT FK_D66C9CACF02F2DDF');
        $this->addSql('ALTER TABLE checklist_items DROP CONSTRAINT FK_DFF66E93B16D08A7');
        $this->addSql('ALTER TABLE equipment_attachments DROP CONSTRAINT FK_26860EF1517FE9FE');
        $this->addSql('ALTER TABLE equipment_tag DROP CONSTRAINT FK_32097FE2BAD26311');
        $this->addSql('ALTER TABLE equipment_tag DROP CONSTRAINT FK_32097FE2517FE9FE');

        $this->addSql('ALTER TABLE inspections DROP CONSTRAINT FK_8625499032C8A3DE');
        $this->addSql('ALTER TABLE checklists DROP CONSTRAINT FK_B0839B3F32C8A3DE');
        $this->addSql('ALTER TABLE equipment_tag_catalog DROP CONSTRAINT FK_485FEBCA32C8A3DE');
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT FK_D338D58332C8A3DE');
    }
}
