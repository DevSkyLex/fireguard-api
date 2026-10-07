<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006100000 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Add declarative asset identity and replacement history to equipment.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE equipment ADD name VARCHAR(255) DEFAULT NULL, ADD asset_code VARCHAR(100) DEFAULT NULL, ADD criticality VARCHAR(16) DEFAULT NULL, ADD technical_properties JSON NOT NULL DEFAULT \'[]\', ADD predecessor_equipment_id VARCHAR(36) DEFAULT NULL, ADD successor_equipment_id VARCHAR(36) DEFAULT NULL');
    $this->addSql('CREATE UNIQUE INDEX uniq_equipment_organization_asset_code ON equipment (organization_id, asset_code)');
    $this->addSql('CREATE UNIQUE INDEX uniq_equipment_predecessor ON equipment (predecessor_equipment_id)');
    $this->addSql('CREATE UNIQUE INDEX uniq_equipment_successor ON equipment (successor_equipment_id)');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP INDEX uniq_equipment_organization_asset_code');
    $this->addSql('DROP INDEX uniq_equipment_predecessor');
    $this->addSql('DROP INDEX uniq_equipment_successor');
    $this->addSql('ALTER TABLE equipment DROP name, DROP asset_code, DROP criticality, DROP technical_properties, DROP predecessor_equipment_id, DROP successor_equipment_id');
  }
}
