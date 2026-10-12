<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Infrastructure\Adapter\Idempotency;

use Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken\{RotateCalendarFeedTokenCommand, RotateCalendarFeedTokenResult};
use Calendar\Infrastructure\Adapter\Idempotency\CalendarFeedTokenIdempotencyProofAdapter;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use User\Application\Contract\User\UserSecurityProof;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function hash;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class CalendarFeedTokenIdempotencyProofAdapterTest
 *
 * A receipt must not disclose a revoked, replaced or newly unauthorized personal feed secret.
 *
 * @category Test
 */
final class CalendarFeedTokenIdempotencyProofAdapterTest extends TestCase
{
  private const string USER = '019c6f20-2000-7000-8000-000000000001';

  private const string ORG = '019c6f20-2000-7000-8000-000000000002';

  private const string TOKEN = '019c6f20-2000-7000-8000-000000000003';

  private const string SECRET = 'local-calendar-proof-fixture';

  /**
   * Method testCurrentLiveTokenAndPermissionPermitExactReplayWithoutPlaintextProof
   */
  public function testCurrentLiveTokenAndPermissionPermitExactReplayWithoutPlaintextProof(): void
  {
    $state = (object) ['permission' => true, 'revision' => 3, 'active' => true, 'live' => true, 'token' => self::TOKEN];
    $adapter = $this->adapter($state);
    $command = new RotateCalendarFeedTokenCommand(self::ORG, self::USER);
    $result = new RotateCalendarFeedTokenResult(self::SECRET, new DateTimeImmutable(), false);
    $facts = $adapter->proofForResult($command, $result);
    self::assertStringNotContainsString(self::SECRET, json_encode($facts, JSON_THROW_ON_ERROR));
    $proof = new IdempotencyProof($facts, new DateTimeImmutable('+5 minutes'));
    self::assertSame($facts, $adapter->authorize($command, $proof)->receiptProof);
    self::assertSame($result, $adapter->replayResult($result, $proof));
    self::assertSame($adapter->authorize($command, null)->canonicalInput, $adapter->authorize($command, $proof)->canonicalInput);
  }

  /**
   * Method testRevocationReplacementPermissionLossAndRevisionChangeAllDenyBeforeDecode
   */
  public function testRevocationReplacementPermissionLossAndRevisionChangeAllDenyBeforeDecode(): void
  {
    $state = (object) ['permission' => true, 'revision' => 3, 'active' => true, 'live' => true, 'token' => self::TOKEN];
    $adapter = $this->adapter($state);
    $command = new RotateCalendarFeedTokenCommand(self::ORG, self::USER);
    $result = new RotateCalendarFeedTokenResult(self::SECRET, new DateTimeImmutable(), false);
    $proof = new IdempotencyProof($adapter->proofForResult($command, $result), new DateTimeImmutable('+5 minutes'));
    $state->live = false;
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
    $state->live = true;
    $state->token = '019c6f20-2000-7000-8000-000000000004';
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
    $state->token = self::TOKEN;
    $state->permission = false;
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
    $state->permission = true;
    $state->revision = 4;
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
    $state->revision = 3;
    $state->active = false;
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
  }

  /**
   * Method adapter
   *
   * @param object{permission: bool, revision: int, active: bool, live: bool, token: string} $state current external authorization and token state
   *
   * @return CalendarFeedTokenIdempotencyProofAdapter boundary under test
   */
  private function adapter(object $state): CalendarFeedTokenIdempotencyProofAdapter
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static fn (): array|false => $state->live ? ['id' => $state->token, 'token_hash' => hash('sha256', self::SECRET)] : false);
    $users = $this->createStub(UserSecurityRevisionPort::class);
    $users->method('current')->willReturnCallback(static fn (): UserSecurityProof => new UserSecurityProof(self::USER, $state->revision, $state->active, true));
    $organizations = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organizations->method('hasPermission')->willReturnCallback(static fn (): bool => $state->permission);

    return new CalendarFeedTokenIdempotencyProofAdapter($connection, $users, $organizations);
  }

  /**
   * Method assertDenied
   *
   * @param callable(): mixed $attempt current secret gate
   */
  private function assertDenied(callable $attempt): void
  {
    try {
      $attempt();
      self::fail('The saved feed secret must be denied.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('access_denied', $failure->reason);
    }
  }
}
