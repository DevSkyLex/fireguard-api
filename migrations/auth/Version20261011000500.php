<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Preserves absolute federated proof instants and their immutable Doctrine type metadata. */
final class Version20261011000500 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Align federated proof instants with immutable timezone-aware Doctrine mapping';
  }

  public function up(Schema $schema): void
  {
    foreach (['exchange_started_at', 'proof_expires_at'] as $column) {
      $type = $this->connection->fetchOne("SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'federated_auth_flows' AND column_name = ?", [$column]);
      if ('timestamp without time zone' === $type) {
        $this->addSql('ALTER TABLE federated_auth_flows ALTER ' . $column . " TYPE TIMESTAMP(0) WITH TIME ZONE USING " . $column . " AT TIME ZONE 'UTC'");
      }
      $this->addSql('COMMENT ON COLUMN federated_auth_flows.' . $column . " IS '(DC2Type:datetimetz_immutable)'");
    }
  }

  public function down(Schema $schema): void
  {
    foreach (['exchange_started_at', 'proof_expires_at'] as $column) {
      $this->addSql('ALTER TABLE federated_auth_flows ALTER ' . $column . " TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING " . $column . " AT TIME ZONE 'UTC'");
      $this->addSql('COMMENT ON COLUMN federated_auth_flows.' . $column . ' IS NULL');
    }
  }
}
