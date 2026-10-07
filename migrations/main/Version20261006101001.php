<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Class Version20261006101001
 *
 * Reconciles immutable date metadata while retaining the existing UTC timestamp values.
 *
 * @category Migration
 */
final class Version20261006101001 extends AbstractMigration
{
  /** Method getDescription. Describes the additive Doctrine metadata correction. */
  public function getDescription(): string
  {
    return 'Register immutable Doctrine types for existing UTC customer timestamps';
  }

  /** Method up. Preserves data and physical UTC timestamp types introduced by Version20261006101000. */
  public function up(Schema $schema): void
  {
    $this->addSql("COMMENT ON COLUMN customers.archived_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN customers.created_at IS '(DC2Type:datetime_immutable)'");
    $this->addSql("COMMENT ON COLUMN customers.updated_at IS '(DC2Type:datetime_immutable)'");
  }

  /** Method down. Restores the original metadata without rewriting any customer date. */
  public function down(Schema $schema): void
  {
    $this->addSql('COMMENT ON COLUMN customers.archived_at IS NULL');
    $this->addSql('COMMENT ON COLUMN customers.created_at IS NULL');
    $this->addSql('COMMENT ON COLUMN customers.updated_at IS NULL');
  }
}
