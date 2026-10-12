<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use User\Application\Contract\User\UserSecurityProof;
use User\Application\Port\Inbound\UserSecurityRevisionPort;
use Webhook\Application\Port\Outbound\WebhookSecretCipherPort;
use Webhook\Application\UseCase\Command\Subscription\RotateWebhookSecret\{RotateWebhookSecretCommand, RotateWebhookSecretResult};
use Webhook\Infrastructure\Adapter\Idempotency\WebhookSecretIdempotencyProofAdapter;

use function array_replace;
use function hash;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class WebhookSecretIdempotencyProofAdapterTest
 *
 * Checks the signed before/after rotation lineage and fresh authority without decrypting stored secrets for a denied caller.
 *
 * @category Test
 */
final class WebhookSecretIdempotencyProofAdapterTest extends TestCase
{
  private const string USER = '019c6f20-3000-7000-8000-000000000001';

  private const string ORG = '019c6f20-3000-7000-8000-000000000002';

  private const string SUBSCRIPTION = '019c6f20-3000-7000-8000-000000000003';

  private const string SECRET = 'local-webhook-proof-fixture';

  /**
   * Method testReplayUsesOriginalCanonicalInputAndRequiresExactPostRotationLineage
   */
  public function testReplayUsesOriginalCanonicalInputAndRequiresExactPostRotationLineage(): void
  {
    $state = (object) ['permission' => true, 'revision' => 3, 'ciphertext' => 'encrypted-original', 'exists' => true];
    $adapter = $this->adapter($state);
    $command = new RotateWebhookSecretCommand(self::ORG, self::USER, self::SUBSCRIPTION);
    $before = $adapter->authorize($command, null);
    $state->ciphertext = 'encrypted-first-rotation';
    $result = $this->fixtureResult();
    $proof = new IdempotencyProof(array_replace($before->receiptProof, $adapter->proofForResult($command, $result)), new DateTimeImmutable('+5 minutes'));
    self::assertSame(hash('sha256', 'encrypted-original'), $proof->metadata['webhookSecretBeforeLineage']);
    self::assertSame(hash('sha256', 'encrypted-first-rotation'), $proof->metadata['webhookSecretAfterLineage']);
    self::assertStringNotContainsString(self::SECRET, json_encode($proof->metadata, JSON_THROW_ON_ERROR));
    self::assertSame($before->canonicalInput, $adapter->authorize($command, $proof)->canonicalInput);
    self::assertSame($result, $adapter->replayResult($result, $proof));
    $state->ciphertext = 'encrypted-later-rotation';
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
  }

  /**
   * Method testLosingAuthorityChangingRevisionOrDeletingSubscriptionDeniesSecret
   */
  public function testLosingAuthorityChangingRevisionOrDeletingSubscriptionDeniesSecret(): void
  {
    $state = (object) ['permission' => true, 'revision' => 3, 'ciphertext' => 'encrypted-current', 'exists' => true];
    $adapter = $this->adapter($state);
    $command = new RotateWebhookSecretCommand(self::ORG, self::USER, self::SUBSCRIPTION);
    $proof = new IdempotencyProof($adapter->proofForResult($command, $this->fixtureResult()), new DateTimeImmutable('+5 minutes'));
    $state->permission = false;
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
    $state->permission = true;
    $state->revision = 4;
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
    $state->revision = 3;
    $state->exists = false;
    $this->assertDenied(fn () => $adapter->authorize($command, $proof));
  }

  /**
   * Method testDeniedCallerNeverDecryptsOwnerSigningSecret
   */
  public function testDeniedCallerNeverDecryptsOwnerSigningSecret(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::never())->method('fetchOne');
    $cipher = $this->createMock(WebhookSecretCipherPort::class);
    $cipher->expects(self::never())->method('decrypt');
    $users = $this->createStub(UserSecurityRevisionPort::class);
    $users->method('current')->willReturn(new UserSecurityProof(self::USER, 3, true, true));
    $organizations = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organizations->method('hasPermission')->willReturn(false);
    $adapter = new WebhookSecretIdempotencyProofAdapter($connection, $users, $organizations, $cipher);
    $this->assertDenied(fn () => $adapter->authorize(new RotateWebhookSecretCommand(self::ORG, self::USER, self::SUBSCRIPTION), null));
  }

  /**
   * Method adapter
   *
   * @param object{permission: bool, revision: int, ciphertext: string, exists: bool} $state fresh external state
   *
   * @return WebhookSecretIdempotencyProofAdapter boundary under test
   */
  private function adapter(object $state): WebhookSecretIdempotencyProofAdapter
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchOne')->willReturnCallback(static fn (): string|false => $state->exists ? $state->ciphertext : false);
    $users = $this->createStub(UserSecurityRevisionPort::class);
    $users->method('current')->willReturnCallback(static fn (): UserSecurityProof => new UserSecurityProof(self::USER, $state->revision, true, true));
    $organizations = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organizations->method('hasPermission')->willReturnCallback(static fn (): bool => $state->permission);
    $cipher = $this->createStub(WebhookSecretCipherPort::class);
    $cipher->method('decrypt')->willReturn(self::SECRET);

    return new WebhookSecretIdempotencyProofAdapter($connection, $users, $organizations, $cipher);
  }

  /**
   * Method fixtureResult
   *
   * @return RotateWebhookSecretResult typed fixture
   */
  private function fixtureResult(): RotateWebhookSecretResult
  {
    return new RotateWebhookSecretResult(self::SUBSCRIPTION, self::ORG, 'https://example.test/webhook', [], true, '', self::SECRET, new DateTimeImmutable(), new DateTimeImmutable());
  }

  /**
   * Method assertDenied
   *
   * @param callable(): mixed $attempt current gate attempt
   */
  private function assertDenied(callable $attempt): void
  {
    try {
      $attempt();
      self::fail('The signing secret must be denied.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('access_denied', $failure->reason);
    }
  }
}
