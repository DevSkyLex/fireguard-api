<?php

declare(strict_types=1);
namespace DoctrineMigrations\Main;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
/**
 * Adds opt-in join policies, distinct DNS challenges and membership requests.
 * User identifiers are scalars; no cross-database foreign keys are introduced.
 * Rollback removes only the new join configuration/history and explicit intent flag.
 * @category Migration
 * @version 1.0.0
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Version20260907130000 extends AbstractMigration
{
  /**
   * @since 1.0.0
   * @return string migration purpose
   */
  public function getDescription(): string { return 'Add organization discovery and join requests with explicit onboarding creation intent'; }
  /**
   * @since 1.0.0
   * @param Schema $schema current schema
   * @return void
   */
  public function up(Schema $schema): void
  {
    $this->addSql('CREATE TABLE organization_access_policies (organization_id VARCHAR(36) NOT NULL, mode VARCHAR(24) NOT NULL, role_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(organization_id))');
    $this->addSql('CREATE TABLE organization_domains (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, domain VARCHAR(253) NOT NULL, dns_value VARCHAR(128) NOT NULL, status VARCHAR(16) NOT NULL, verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql('CREATE UNIQUE INDEX uniq_org_domain_pair ON organization_domains (organization_id, domain)');
    $this->addSql('CREATE INDEX idx_org_domain_exact ON organization_domains (domain)');
    $this->addSql('CREATE TABLE organization_join_requests (id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, email VARCHAR(254) NOT NULL, domain_id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(16) NOT NULL, decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    $this->addSql("CREATE UNIQUE INDEX uniq_org_join_pending ON organization_join_requests (organization_id, user_id) WHERE status = 'pending'");
    $this->addSql('CREATE INDEX idx_org_join_applicant ON organization_join_requests (user_id)');
    foreach (['organization_domains' => ['verified_at', 'last_checked_at'], 'organization_join_requests' => ['created_at', 'expires_at', 'decided_at']] as $table => $columns) {
      foreach ($columns as $column) { $this->addSql("COMMENT ON COLUMN $table.$column IS '(DC2Type:datetime_immutable)'"); }
    }
    $this->addSql('ALTER TABLE organization_onboarding_sessions ADD creation_intent BOOLEAN DEFAULT FALSE NOT NULL');
  }
  /**
   * @since 1.0.0
   * @param Schema $schema current schema
   * @return void
   */
  public function down(Schema $schema): void
  {
    $this->addSql('DROP TABLE organization_join_requests');
    $this->addSql('DROP TABLE organization_domains');
    $this->addSql('DROP TABLE organization_access_policies');
    $this->addSql('ALTER TABLE organization_onboarding_sessions DROP creation_intent');
  }
}
