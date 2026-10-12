<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Calendar\Application\Port\Outbound\Feed\CalendarFeedDocumentWriterPort;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, ParameterType};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use Tests\Support\Kernel\StrictHttpKernel;

use function hash;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Exercises the strict subscription GET with true main commits, native revocations and original document replay.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class CalendarFeedIcsIdempotencyApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'b2c3d4e5-f6a7-4901-8cde-f23456789012';

  private const string SECRET = 'calendar_feed_private_test_subscription_secret';

  private const string TOKEN = '55555555-1111-4111-8111-111111111111';

  private const string EVENT = '66666666-1111-4111-8111-111111111111';

  private const string ROLE = '77777777-1111-4111-8111-111111111111';

  private Connection $main;

  private Connection $auth;

  private KernelBrowser $browser;

  private string $memberId;

  protected function setUp(): void
  {
    $this->browser = static::createClient();
    $main = static::getContainer()->get('doctrine.dbal.main_connection');
    $auth = static::getContainer()->get('doctrine.dbal.auth_connection');
    self::assertInstanceOf(Connection::class, $main);
    self::assertInstanceOf(Connection::class, $auth);
    $this->main = $main;
    $this->auth = $auth;
    $member = $main->fetchOne('SELECT id FROM organization_members WHERE organization_id = ? AND user_id = ?', [OrganizationFixtures::ORGANIZATION_ID, self::USER]);
    self::assertIsString($member);
    $this->memberId = $member;
    $main->delete('organization_member_roles', ['member_id' => $member]);
    $main->insert('organization_roles', ['id' => self::ROLE, 'organization_id' => OrganizationFixtures::ORGANIZATION_ID, 'name' => 'ics_subscriber', 'permissions' => '["organization.events.read","organization.inspection.read"]', 'description' => '', 'is_system' => false, 'created_at' => '2026-10-11 12:00:00'], ['is_system' => ParameterType::BOOLEAN]);
    $main->insert('organization_member_roles', ['member_id' => $member, 'role_id' => self::ROLE, 'assigned_at' => '2026-10-11 12:00:00']);
    $main->insert('member_calendar_feed_tokens', ['id' => self::TOKEN, 'organization_id' => OrganizationFixtures::ORGANIZATION_ID, 'user_id' => self::USER, 'token_hash' => hash('sha256', self::SECRET), 'created_at' => '2026-10-11 12:00:00']);
    $main->insert('calendar_events', ['id' => self::EVENT, 'organization_id' => OrganizationFixtures::ORGANIZATION_ID, 'title' => 'Original poll title', 'starts_at' => new DateTimeImmutable('+1 day')->format('Y-m-d H:i:s'), 'all_day' => false, 'created_by_member_id' => $member, 'created_at' => '2026-10-11 12:00:00', 'updated_at' => '2026-10-11 12:00:00'], ['all_day' => ParameterType::BOOLEAN]);
  }

  public function testStrictHeaderIsRequiredOnlyAfterTheUniformFreshCredentialGate(): void
  {
    $this->poll();
    self::assertSame(400, $this->browser->getResponse()->getStatusCode());
    self::assertStringContainsString('idempotency_key_required', (string) $this->browser->getResponse()->getContent());
    self::assertNull($this->main->fetchOne('SELECT last_used_at FROM member_calendar_feed_tokens WHERE id = ?', [self::TOKEN]));
    self::assertSame(0, $this->main->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
    $this->browser->request('GET', '/api/calendar/feed/unknown-private-token.ics');
    self::assertSame(404, $this->browser->getResponse()->getStatusCode());
    $this->main->update('member_calendar_feed_tokens', ['revoked_at' => '2026-10-11 12:00:00'], ['id' => self::TOKEN]);
    $this->poll();
    self::assertSame(404, $this->browser->getResponse()->getStatusCode());
  }

  public function testSamePollKeyReplaysOriginalBytesWithoutUsageWhileNewKeyReadsCurrentContent(): void
  {
    $this->poll('poll-one');
    self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
    $original = $this->browser->getResponse()->getContent();
    self::assertIsString($original);
    self::assertStringContainsString('Original poll title', $original);
    self::assertStringNotContainsString(self::SECRET, $original);
    self::assertIsString($this->main->fetchOne('SELECT last_used_at FROM member_calendar_feed_tokens WHERE id = ?', [self::TOKEN]));
    $this->main->update('calendar_events', ['title' => 'Current second poll title'], ['id' => self::EVENT]);
    $this->main->update('member_calendar_feed_tokens', ['last_used_at' => '2000-01-01 00:00:00'], ['id' => self::TOKEN]);
    $this->poll('poll-one');
    self::assertSame(200, $this->browser->getResponse()->getStatusCode());
    self::assertSame($original, $this->browser->getResponse()->getContent());
    self::assertSame('2000-01-01 00:00:00', $this->main->fetchOne('SELECT last_used_at FROM member_calendar_feed_tokens WHERE id = ?', [self::TOKEN]));
    $this->poll('poll-two');
    self::assertSame(200, $this->browser->getResponse()->getStatusCode());
    self::assertStringContainsString('Current second poll title', (string) $this->browser->getResponse()->getContent());
    self::assertNotSame($original, $this->browser->getResponse()->getContent());
    $used = $this->main->fetchOne('SELECT last_used_at FROM member_calendar_feed_tokens WHERE id = ?', [self::TOKEN]);
    self::assertNotSame('2000-01-01 00:00:00', $used);
    $this->poll('poll-three');
    self::assertSame(200, $this->browser->getResponse()->getStatusCode());
    self::assertSame($used, $this->main->fetchOne('SELECT last_used_at FROM member_calendar_feed_tokens WHERE id = ?', [self::TOKEN]), 'A fresh poll inside one hour keeps the usage throttle.');
    $receipts = $this->main->fetchAllAssociative('SELECT * FROM http_idempotency_receipts');
    self::assertCount(3, $receipts);
    foreach ($receipts as $receipt) {
      self::assertStringNotContainsString(self::SECRET, json_encode($receipt, JSON_THROW_ON_ERROR));
      self::assertSame('completed', $receipt['state']);
      self::assertFalse($receipt['secret_result']);
      self::assertIsString($receipt['result_expires_at']);
      self::assertIsString($receipt['completed_at']);
      self::assertGreaterThan(23 * 3600, new DateTimeImmutable($receipt['result_expires_at'])->getTimestamp() - new DateTimeImmutable($receipt['completed_at'])->getTimestamp());
    }
  }

  #[DataProvider('revocations')]
  public function testEveryReplayChecksFreshOwnerFactsBeforeDisclosingOriginalBytes(string $revocation): void
  {
    $this->poll('original-private-poll');
    self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
    match ($revocation) {
      'token' => $this->main->update('member_calendar_feed_tokens', ['revoked_at' => '2026-10-11 12:00:00'], ['id' => self::TOKEN]),
      'membership' => $this->main->update('organization_members', ['is_active' => false], ['id' => $this->memberId], ['is_active' => ParameterType::BOOLEAN]),
      'permission' => $this->main->update('organization_roles', ['permissions' => '[]'], ['id' => self::ROLE]),
      'source' => $this->main->update('organization_roles', ['permissions' => '["organization.events.read"]'], ['id' => self::ROLE]),
      'revision' => $this->auth->executeStatement('UPDATE users SET security_revision = security_revision + 1 WHERE id = ?', [self::USER]),
      'tenant' => $this->auth->update('users', ['tenant_id' => '99999999-1111-4111-8111-111111111111'], ['id' => self::USER]),
      'inactive', 'locked', 'pending_verification' => $this->auth->update('users', ['status' => $revocation], ['id' => self::USER]),
      default => self::fail('Unknown test revocation'),
    };
    $this->poll('original-private-poll');
    self::assertSame(404, $this->browser->getResponse()->getStatusCode());
    self::assertStringNotContainsString('Original poll title', (string) $this->browser->getResponse()->getContent());
    self::assertSame(1, $this->main->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  /**
   * @return iterable<string, array{string}> fresh owner revocations
   */
  public static function revocations(): iterable
  {
    foreach (['token', 'membership', 'permission', 'source', 'revision', 'tenant', 'inactive', 'locked', 'pending_verification'] as $case) {
      yield $case => [$case];
    }
  }

  public function testFailedFinalDisclosureProofRollsBackUsageAndTheReceiptTogether(): void
  {
    $this->browser->disableReboot();
    $writer = $this->createMock(CalendarFeedDocumentWriterPort::class);
    $writer->expects(self::once())->method('write')->willReturnCallback(function (): string {
      self::assertTrue($this->main->isTransactionActive());
      $this->main->update('organization_roles', ['permissions' => '[]'], ['id' => self::ROLE]);

      return "BEGIN:VCALENDAR\r\nOriginal forbidden bytes\r\nEND:VCALENDAR\r\n";
    });
    static::getContainer()->set(CalendarFeedDocumentWriterPort::class, $writer);
    $this->poll('final-proof-denied');
    self::assertSame(404, $this->browser->getResponse()->getStatusCode());
    self::assertStringNotContainsString('Original forbidden bytes', (string) $this->browser->getResponse()->getContent());
    self::assertNull($this->main->fetchOne('SELECT last_used_at FROM member_calendar_feed_tokens WHERE id = ?', [self::TOKEN]));
    self::assertSame('["organization.events.read","organization.inspection.read"]', $this->main->fetchOne('SELECT permissions FROM organization_roles WHERE id = ?', [self::ROLE]));
    self::assertSame(0, $this->main->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
    self::assertNull($this->main->fetchOne('SELECT result_payload FROM http_idempotency_receipts LIMIT 1'));
  }

  public function testAnUnrelatedAuthenticatedTenantReplaysTheSameAnonymousFeedExecution(): void
  {
    $this->poll('ambient-tenant-poll');
    self::assertSame(200, $this->browser->getResponse()->getStatusCode());
    $original = $this->browser->getResponse()->getContent();
    self::assertIsString($original);
    $this->auth->update('users', ['tenant_id' => '88888888-1111-4111-8111-111111111111'], ['id' => 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890']);
    $this->browser->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IDEMPOTENCY_KEY' => 'ambient-tenant-login'], content: '{"email":"admin@fireguard.local","password":"Admin123!"}');
    self::assertSame(200, $this->browser->getResponse()->getStatusCode());
    $login = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($login);
    self::assertIsString($login['access_token'] ?? null);
    $this->main->update('calendar_events', ['title' => 'Content after original anonymous poll'], ['id' => self::EVENT]);
    $this->main->update('member_calendar_feed_tokens', ['last_used_at' => '2000-01-01 00:00:00'], ['id' => self::TOKEN]);
    $this->browser->request('GET', '/api/calendar/feed/' . self::SECRET . '.ics', server: ['HTTP_IDEMPOTENCY_KEY' => 'ambient-tenant-poll', 'HTTP_AUTHORIZATION' => 'Bearer ' . $login['access_token']]);
    self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
    self::assertSame($original, $this->browser->getResponse()->getContent());
    self::assertSame('true', $this->browser->getResponse()->headers->get('Idempotency-Replayed'));
    self::assertSame('2000-01-01 00:00:00', $this->main->fetchOne('SELECT last_used_at FROM member_calendar_feed_tokens WHERE id = ?', [self::TOKEN]));
    self::assertSame(1, $this->main->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  /**
   * @return class-string<StrictHttpKernel> production strict contract
   */
  protected static function getKernelClass(): string
  {
    return StrictHttpKernel::class;
  }

  private function poll(?string $key = null): void
  {
    $this->browser->request('GET', '/api/calendar/feed/' . self::SECRET . '.ics', server: null === $key ? [] : ['HTTP_IDEMPOTENCY_KEY' => $key]);
  }
}
