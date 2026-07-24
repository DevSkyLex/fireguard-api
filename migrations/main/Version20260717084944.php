<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Domain Version20260717084944.
 *
 * R9: adds `teams` and `team_members` (main DB) inside the existing
 * Organization module — groups of organization members with an org-scoped
 * CRUD + membership API. `team_members` is a join table (composite PK
 * team_id/member_id) with cascading FKs to `teams` and
 * `organization_members`; `role` is a free-form membership label, not an
 * RBAC role. Uniqueness is enforced at the database level via
 * `uniq_team_org_name` (organization_id, name).
 *
 * Hand-pruned from the raw `doctrine:migrations:diff` output: the auto-diff
 * also picked up unrelated auth-database/cross-entity-manager drift
 * (messenger/OAuth2/session/role tables, timestamp precision and default
 * normalizations on unrelated main-DB tables). None of that belongs to this
 * migration; only the `teams`/`team_members` DDL is kept here.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260717084944 extends AbstractMigration
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
    return 'Add teams and team_members tables (organization teams + membership)';
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

    $this->addSql('CREATE TABLE teams (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, name VARCHAR(80) NOT NULL, description TEXT DEFAULT \'\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE INDEX idx_team_org ON teams (organization_id)');
    $this->addSql('CREATE UNIQUE INDEX uniq_team_org_name ON teams (organization_id, name)');
    $this->addSql('COMMENT ON COLUMN teams.created_at IS \'(DC2Type:datetime_immutable)\'');
    $this->addSql('COMMENT ON COLUMN teams.updated_at IS \'(DC2Type:datetime_immutable)\'');

    $this->addSql('CREATE TABLE team_members (team_id VARCHAR(36) NOT NULL, member_id VARCHAR(36) NOT NULL, role VARCHAR(50) DEFAULT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(team_id, member_id))');
    $this->addSql('CREATE INDEX IDX_BAD9A3C8296CD8AE ON team_members (team_id)');
    $this->addSql('CREATE INDEX idx_team_members_member ON team_members (member_id)');
    $this->addSql('COMMENT ON COLUMN team_members.added_at IS \'(DC2Type:datetime_immutable)\'');

    $this->addSql('ALTER TABLE teams ADD CONSTRAINT FK_96C2225832C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE team_members ADD CONSTRAINT FK_BAD9A3C8296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    $this->addSql('ALTER TABLE team_members ADD CONSTRAINT FK_BAD9A3C87597D3FE FOREIGN KEY (member_id) REFERENCES organization_members (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
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

    $this->addSql('ALTER TABLE team_members DROP CONSTRAINT FK_BAD9A3C8296CD8AE');
    $this->addSql('ALTER TABLE team_members DROP CONSTRAINT FK_BAD9A3C87597D3FE');
    $this->addSql('ALTER TABLE teams DROP CONSTRAINT FK_96C2225832C8A3DE');
    $this->addSql('DROP TABLE team_members');
    $this->addSql('DROP TABLE teams');
  }
}
