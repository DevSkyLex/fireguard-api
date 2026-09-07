<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Durable organization setup recovery.
 *
 * @category Migration
 * @version 1.0.0
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */

final class Version20260907161534 extends AbstractMigration
{
  /** @since 1.0.0 */
  public function getDescription(): string { return 'Persist durable onboarding setup operation inputs and created identifiers in main.'; }
  /** @since 1.0.0 */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE organization_onboarding_sessions ADD setup_operations JSONB DEFAULT \'[]\' NOT NULL');
  }
  /** @since 1.0.0 Rollback removes the setup recovery journal; business resources remain. */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE organization_onboarding_sessions DROP setup_operations');
  }
}
