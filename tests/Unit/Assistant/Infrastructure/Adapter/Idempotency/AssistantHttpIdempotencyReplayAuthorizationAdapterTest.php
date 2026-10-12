<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Infrastructure\Adapter\Idempotency;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\Port\Outbound\Internal\AssistantThreadRepositoryPort;
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\AssistantAccessPolicy;
use Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt\ControlAssistantAttemptResult;
use Assistant\Application\UseCase\Command\Thread\StartAssistantThread\{StartAssistantThreadCommand, StartAssistantThreadHandler, StartAssistantThreadResult};
use Assistant\Domain\Exception\{AssistantDisabledException, AssistantThreadNotFoundException};
use Assistant\Domain\Service\AssistantModelPolicy;
use Assistant\Infrastructure\Adapter\Idempotency\{AssistantHttpIdempotencyOperationContributionAdapter, AssistantHttpIdempotencyReplayAuthorizationAdapter};
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;
use Tests\Support\Idempotency\OwnerReplayTestSupport;
use Throwable;

/** @category Unit Test */
final class AssistantHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  use OwnerReplayTestSupport;

  /**
   * @param class-string<Throwable> $exception
   */
  #[DataProvider('lostAuthority')]
  public function testChangedAuthorityDeniesBeforeReturningConversationResult(string $change, string $exception): void
  {
    $permissions = ['organization.assistant.use'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $owner = self::ACTOR;
    $enabled = true;
    $threadOrganization = self::ORGANIZATION;
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql) use (&$owner, &$threadOrganization): array {
      return ['organization_id' => $threadOrganization, 'member_id' => $owner, 'thread_id' => self::TARGET];
    });
    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturnCallback(static function () use (&$enabled): bool { return $enabled; });
    $gate = new AssistantHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $settings, $connection, $evidence);
    $operation = $this->operation(AssistantHttpIdempotencyOperationContributionAdapter::definitions(), 'assistant_retry_generation');
    $request = $this->request($operation, ['organizationId' => self::ORGANIZATION, 'threadId' => self::TARGET, 'messageId' => self::CHILD]);
    $proof = $this->proof($request, $gate->authorize($request, null), self::CHILD, $permissions);
    self::assertSame(self::TARGET, $gate->authorize($request, $proof)['assistantThreadId']);
    match ($change) {
      'permission' => $permissions = [],
      'creator' => $owner = self::RELATED,
      'organization' => $threadOrganization = self::RELATED,
      default => $enabled = false,
    };
    $this->expectException($exception);
    $gate->authorize($request, $proof);
  }

  /**
   * @return iterable<string,array{string,class-string<Throwable>}>
   */
  public static function lostAuthority(): iterable
  {
    yield 'revoked grant' => ['permission', IdempotencyDeniedException::class];
    yield 'different creator' => ['creator', AssistantThreadNotFoundException::class];
    yield 'foreign thread' => ['organization', AssistantThreadNotFoundException::class];
    yield 'kill switch off' => ['enabled', AssistantDisabledException::class];
  }

  public function testMessageInAnotherThreadCannotRestoreAnAuthorizedThreadReceipt(): void
  {
    $permissions = ['organization.assistant.use'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn(true);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static fn (string $sql): array => ['organization_id' => self::ORGANIZATION, 'member_id' => self::ACTOR, 'thread_id' => self::RELATED]);
    $gate = new AssistantHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $settings, $connection, $evidence);
    $request = $this->request($this->operation(AssistantHttpIdempotencyOperationContributionAdapter::definitions(), 'assistant_cancel_generation'), ['organizationId' => self::ORGANIZATION, 'threadId' => self::TARGET, 'messageId' => self::CHILD]);
    $this->expectException(AssistantThreadNotFoundException::class);
    $gate->authorize($request, null);
  }

  public function testOriginalPendingMessageSnapshotIsNotReplacedByWorkerProgress(): void
  {
    $operation = $this->operation(AssistantHttpIdempotencyOperationContributionAdapter::definitions(), 'assistant_retry_generation');
    $view = new AssistantMessageView(self::CHILD, self::TARGET, self::ORGANIZATION, 'assistant', '', 'pending', null, null, new DateTimeImmutable('2026-01-01'), null, self::RELATED, 2);
    $codec = new HttpIdempotencyResultCodec();
    $stored = $codec->encode(new ControlAssistantAttemptResult($view), $operation);
    $decoded = $codec->decode($stored, $operation);
    self::assertInstanceOf(ControlAssistantAttemptResult::class, $decoded);
    self::assertSame('pending', $decoded->message->status);
    self::assertSame(self::RELATED, $decoded->message->attemptId);
    self::assertSame(2, $decoded->message->attemptNumber);
    self::assertSame('', $decoded->message->body);
  }

  public function testActualThreadCreationResultReplaysWithoutAnotherSaveOrEvent(): void
  {
    $permissions = ['organization.assistant.use'];
    [$ordinary, $organizations, $evidence] = $this->authorities($permissions);
    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn(true);
    $threads = $this->createMock(AssistantThreadRepositoryPort::class);
    $threads->expects(self::once())->method('save');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch');
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::TARGET);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-01'));
    $handler = new StartAssistantThreadHandler($threads, new AssistantAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $settings), new AssistantModelPolicy([]), $ids, $events, $clock);
    $original = $handler(new StartAssistantThreadCommand(self::ORGANIZATION, self::ACTOR, 'Original title'));
    $operation = $this->operation(AssistantHttpIdempotencyOperationContributionAdapter::definitions(), 'startAssistantThread');
    $codec = new HttpIdempotencyResultCodec();
    $snapshot = $codec->encode($original, $operation);
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => self::ORGANIZATION, 'member_id' => self::ACTOR, 'title' => 'Later title']);
    $gate = new AssistantHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organizations, $settings, $connection, $evidence);
    $request = $this->request($operation, ['organizationId' => self::ORGANIZATION]);
    $proof = $this->proof($request, $gate->authorize($request, null), self::TARGET, $permissions);
    $gate->authorize($request, $proof);
    $restored = $codec->decode($snapshot, $operation);
    self::assertInstanceOf(StartAssistantThreadResult::class, $restored);
    self::assertEquals($original, $restored);
    self::assertSame('Original title', $restored->thread->title);
  }
}
