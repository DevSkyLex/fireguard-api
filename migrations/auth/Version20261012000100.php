<?php

declare(strict_types=1);

namespace DoctrineMigrations\Auth;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Keeps security epochs monotonic across concurrent owner persistence writes.
 *
 * @category Migration
 */
final class Version20261012000100 extends AbstractMigration
{
  public function getDescription(): string
  {
    return 'Advance User and OAuth client security revisions for every persisted security mutation.';
  }

  public function up(Schema $schema): void
  {
    $this->addSql(<<<'SQL'
      CREATE FUNCTION fireguard_user_security_revision() RETURNS TRIGGER LANGUAGE plpgsql AS $$
      BEGIN
        IF ROW(NEW.password, NEW.email, NEW.username, NEW.status, NEW.email_verified, NEW.email_ownership_verified_at, NEW.tenant_id)
          IS DISTINCT FROM ROW(OLD.password, OLD.email, OLD.username, OLD.status, OLD.email_verified, OLD.email_ownership_verified_at, OLD.tenant_id) THEN
          NEW.security_revision := GREATEST(OLD.security_revision + 1, NEW.security_revision);
        ELSE
          NEW.security_revision := GREATEST(OLD.security_revision, NEW.security_revision);
        END IF;
        RETURN NEW;
      END;
      $$
      SQL);
    $this->addSql('CREATE TRIGGER trg_user_security_revision BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION fireguard_user_security_revision()');
    $this->addSql(<<<'SQL'
      CREATE FUNCTION fireguard_client_security_revision() RETURNS TRIGGER LANGUAGE plpgsql AS $$
      BEGIN
        IF ROW(NEW.secret, NEW.name, NEW.redirect_uris::jsonb, NEW.grant_types::jsonb, NEW.scopes::jsonb, NEW.is_active, NEW.deleted_at)
          IS DISTINCT FROM ROW(OLD.secret, OLD.name, OLD.redirect_uris::jsonb, OLD.grant_types::jsonb, OLD.scopes::jsonb, OLD.is_active, OLD.deleted_at) THEN
          NEW.security_revision := GREATEST(OLD.security_revision + 1, NEW.security_revision);
        ELSE
          NEW.security_revision := GREATEST(OLD.security_revision, NEW.security_revision);
        END IF;
        RETURN NEW;
      END;
      $$
      SQL);
    $this->addSql('CREATE TRIGGER trg_client_security_revision BEFORE UPDATE ON clients FOR EACH ROW EXECUTE FUNCTION fireguard_client_security_revision()');
  }

  public function down(Schema $schema): void
  {
    $this->addSql('DROP TRIGGER trg_client_security_revision ON clients');
    $this->addSql('DROP FUNCTION fireguard_client_security_revision()');
    $this->addSql('DROP TRIGGER trg_user_security_revision ON users');
    $this->addSql('DROP FUNCTION fireguard_user_security_revision()');
  }
}
