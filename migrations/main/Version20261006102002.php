<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Preserve existing UTC timestamps while making their immutable Doctrine type explicit. */
final class Version20261006102002 extends AbstractMigration
{
  /** @var array<string, list<string>> */
  private const array COLUMNS = [
    'maintenance_plans' => ['anchor_at', 'next_due_at', 'last_completed_at', 'archived_at', 'created_at', 'updated_at'],
    'maintenance_occurrences' => ['due_at', 'completed_at', 'created_at', 'updated_at'],
    'maintenance_engines' => ['activated_at'],
    'maintenance_operation_receipts' => ['performed_at'],
  ];

  public function getDescription(): string
  {
    return 'Annotate immutable maintenance timestamps for stable Doctrine schema comparison.';
  }

  public function up(Schema $schema): void
  {
    foreach (self::COLUMNS as $table => $columns) {
      foreach ($columns as $column) {
        $this->addSql('COMMENT ON COLUMN ' . $table . '.' . $column . " IS '(DC2Type:datetime_immutable)'");
      }
    }
  }

  public function down(Schema $schema): void
  {
    foreach (self::COLUMNS as $table => $columns) {
      foreach ($columns as $column) {
        $this->addSql('COMMENT ON COLUMN ' . $table . '.' . $column . ' IS NULL');
      }
    }
  }
}
