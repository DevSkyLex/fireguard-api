<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Adapter\Idempotency;

use Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken\{RotateCalendarFeedTokenCommand, RotateCalendarFeedTokenResult};
use Calendar\Infrastructure\Adapter\Idempotency\CalendarFeedTokenIdempotencyProofAdapter;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Messaging\Infrastructure\Adapter\Idempotency\{MessagingHttpIdempotencyOperationContributionAdapter, MessagingHttpIdempotencyReplayAuthorizationAdapter};
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;
use User\Application\Contract\User\UserSecurityProof;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function array_replace;
use function hash;
use function parse_url;

use const PHP_URL_PATH;

/**
 * Class OrdinaryReplayFreshContextTest
 *
 * Uses independent PostgreSQL connections so removed participation and token revocation must be observed without rebuilding an adapter or clearing an entity manager.
 *
 * @category Integration Test
 */
final class OrdinaryReplayFreshContextTest extends TestCase
{
  private const string USER = '019c6f20-5000-7000-8000-000000000001';

  private const string MEMBER = '019c6f20-5000-7000-8000-000000000002';

  private const string CONVERSATION = '019c6f20-5000-7000-8000-000000000003';

  private const string MESSAGE = '019c6f20-5000-7000-8000-000000000004';

  private const string TOKEN = '019c6f20-5000-7000-8000-000000000005';

  private const string ORG = '019c6f20-5000-7000-8000-000000000006';

  /**
   * Method testCommittedParticipantRemovalAndTombstoneDenyContentButAllowOwnedCleanup
   */
  public function testCommittedParticipantRemovalAndTombstoneDenyContentButAllowOwnedCleanup(): void
  {
    $reader = $this->connection('MAIN_DATABASE_URL');
    $writer = $this->connection('MAIN_DATABASE_URL');
    $organizationId = $reader->fetchOne('SELECT id FROM organizations ORDER BY id LIMIT 1');
    self::assertIsString($organizationId);
    $now = new DateTimeImmutable()->format('Y-m-d H:i:s');
    $reader->insert('messaging_conversations', ['id' => self::CONVERSATION, 'organization_id' => $organizationId, 'subject_type' => 'channel', 'subject_id' => null, 'visibility' => 'participants', 'messages_count' => 1, 'is_archived' => 'false', 'created_at' => $now, 'updated_at' => $now]);
    $reader->insert('messaging_messages', ['id' => self::MESSAGE, 'conversation_id' => self::CONVERSATION, 'organization_id' => $organizationId, 'author_member_id' => self::MEMBER, 'body' => 'original fixture content', 'mentions' => '[]', 'created_at' => $now, 'updated_at' => $now]);
    $reader->insert('messaging_participants', ['conversation_id' => self::CONVERSATION, 'member_id' => self::MEMBER, 'organization_id' => $organizationId, 'source' => 'manual', 'added_at' => $now]);
    $organizations = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organizations->method('activeMemberId')->willReturn(self::MEMBER);
    $organizations->method('hasPermission')->willReturnCallback(static fn (string $user, string $org, string $permission): bool => $org === $organizationId && 'organization.messaging.manage' !== $permission);
    $ordinary = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->method('authorize')->willReturnCallback(static fn (HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, ?string $organization): array => null === $proof ? ['organizationId' => $organization] : $proof->metadata);
    $adapter = new MessagingHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $reader, []);

    try {
      $save = $this->request('save_message');
      $proof = $this->proof($adapter->authorize($save, null), 'messaging_message', self::MESSAGE);
      self::assertSame(self::MEMBER, $adapter->authorize($save, $proof)['messagingMemberId']);
      $writer->delete('messaging_participants', ['conversation_id' => self::CONVERSATION, 'member_id' => self::MEMBER]);
      $this->assertDenied(fn () => $adapter->authorize($save, $proof));

      $cleanup = $this->request('unsave_message');
      $cleanupProof = $this->proof($adapter->authorize($cleanup, null), 'messaging_message', self::MESSAGE);
      self::assertSame($organizationId, $adapter->authorize($cleanup, $cleanupProof)['organizationId']);
      $writer->insert('messaging_participants', ['conversation_id' => self::CONVERSATION, 'member_id' => self::MEMBER, 'organization_id' => $organizationId, 'source' => 'manual', 'added_at' => $now]);
      $writer->update('messaging_messages', ['deleted_at' => $now], ['id' => self::MESSAGE]);
      $this->assertDenied(fn () => $adapter->authorize($save, $proof));
      self::assertSame('true', $adapter->authorize($cleanup, $cleanupProof)['messagingTargetDeleted']);
    } finally {
      $reader->delete('messaging_conversations', ['id' => self::CONVERSATION]);
      $reader->close();
      $writer->close();
    }
  }

  /**
   * Method testCommittedTokenRevocationDeniesThePreviouslyValidSecretReceipt
   */
  public function testCommittedTokenRevocationDeniesThePreviouslyValidSecretReceipt(): void
  {
    $reader = $this->connection('MAIN_DATABASE_URL');
    $writer = $this->connection('MAIN_DATABASE_URL');
    $now = new DateTimeImmutable();
    $secret = 'local-native-calendar-proof-fixture';
    $reader->insert('member_calendar_feed_tokens', ['id' => self::TOKEN, 'organization_id' => self::ORG, 'user_id' => self::USER, 'token_hash' => hash('sha256', $secret), 'created_at' => $now->format('Y-m-d H:i:s')]);
    $organizations = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organizations->method('hasPermission')->willReturn(true);
    $users = $this->createStub(UserSecurityRevisionPort::class);
    $users->method('current')->willReturn(new UserSecurityProof(self::USER, 1, true, true));
    $adapter = new CalendarFeedTokenIdempotencyProofAdapter($reader, $users, $organizations);
    $command = new RotateCalendarFeedTokenCommand(self::ORG, self::USER);

    try {
      $proof = new IdempotencyProof($adapter->proofForResult($command, new RotateCalendarFeedTokenResult($secret, $now, false)), new DateTimeImmutable('+5 minutes'));
      self::assertSame(self::TOKEN, $adapter->authorize($command, $proof)->receiptProof['calendarFeedTokenId']);
      $writer->update('member_calendar_feed_tokens', ['revoked_at' => $now->format('Y-m-d H:i:s')], ['id' => self::TOKEN]);
      $this->assertDenied(fn () => $adapter->authorize($command, $proof));
    } finally {
      $reader->delete('member_calendar_feed_tokens', ['id' => self::TOKEN]);
      $reader->close();
      $writer->close();
    }
  }

  /**
   * Method connection
   *
   * @param string $variable explicit owner test DSN
   *
   * @return Connection independent native PostgreSQL connection
   */
  private function connection(string $variable): Connection
  {
    $url = $_ENV[$variable] ?? $_SERVER[$variable] ?? null;
    self::assertIsString($url);
    self::assertMatchesRegularExpression('/_test_w[A-Za-z0-9_]+$/', (string) parse_url($url, PHP_URL_PATH));

    return DriverManager::getConnection(['url' => $url]);
  }

  /**
   * Method request
   *
   * @param string $name exact registered mutation
   *
   * @return HttpIdempotencyAuthorizationRequest owner request
   */
  private function request(string $name): HttpIdempotencyAuthorizationRequest
  {
    foreach (MessagingHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $name) {
        return new HttpIdempotencyAuthorizationRequest(self::USER, null, $operation, '/api/test', ['id' => self::MESSAGE], []);
      }
    }
    self::fail('Missing owner mutation.');
  }

  /**
   * Method proof
   *
   * @param array<string, string> $facts original authorized scope
   * @param string $kind exact resource kind
   * @param string $id original result identifier
   *
   * @return IdempotencyProof authenticated original result scope
   */
  private function proof(array $facts, string $kind, string $id): IdempotencyProof
  {
    return new IdempotencyProof(array_replace($facts, ['resourceKind' => $kind, 'resourceId' => $id]), new DateTimeImmutable('+5 minutes'));
  }

  /**
   * Method assertDenied
   *
   * @param callable(): mixed $attempt current replay gate
   */
  private function assertDenied(callable $attempt): void
  {
    try {
      $attempt();
      self::fail('Committed external state changes must deny saved content.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('access_denied', $failure->reason);
    }
  }
}
