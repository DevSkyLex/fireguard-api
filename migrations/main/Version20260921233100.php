<?php

declare(strict_types=1);
namespace DoctrineMigrations\Main;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921233100 extends AbstractMigration
{
  public function getDescription(): string { return 'Preserve the immutable type of assistant attempt deadlines in schema introspection.'; }
  public function up(Schema $schema): void
  {
    $this->addSql("COMMENT ON COLUMN assistant_messages.attempt_expires_at IS '(DC2Type:datetime_immutable)'");
  }
  public function down(Schema $schema): void
  {
    $this->addSql('COMMENT ON COLUMN assistant_messages.attempt_expires_at IS NULL');
  }
}
