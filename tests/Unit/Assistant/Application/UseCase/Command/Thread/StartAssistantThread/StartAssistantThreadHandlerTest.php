<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Thread\StartAssistantThread;

use Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort;
use Assistant\Application\UseCase\Command\Thread\StartAssistantThread\{StartAssistantThreadCommand, StartAssistantThreadHandler};
use Assistant\Domain\Event\Thread\AssistantThreadStartedEvent;
use Assistant\Domain\Exception\AssistantValidationException;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\Service\AssistantModelPolicy;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};

/**
 * Test StartAssistantThreadHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(StartAssistantThreadHandler::class)]
final class StartAssistantThreadHandlerTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c04';

  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c05';

  #[Test]
  public function testInvokeThrowsWhenPermissionMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      OrganizationAccessDeniedException::missingPermission('organization.assistant.use'),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $this->handler(authorization: $authorization)(new StartAssistantThreadCommand(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeSavesTheThreadAndDispatchesTheStartedEvent(): void
  {
    $threads = $this->createMock(AssistantThreadRepositoryPort::class);
    $threads->expects(self::once())->method('save')->with(self::isInstanceOf(AssistantThread::class));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (AssistantThreadStartedEvent $event): bool {
        self::assertSame(self::ORG_ID, $event->organizationId);
        self::assertSame(self::USER_ID, $event->memberId);

        return true;
      }));

    $result = $this->handler(threads: $threads, eventDispatcher: $eventDispatcher)(
      new StartAssistantThreadCommand(self::ORG_ID, self::USER_ID, 'Fire safety questions'),
    );

    self::assertSame(self::ORG_ID, $result->thread->organizationId);
    self::assertSame(self::USER_ID, $result->thread->memberId);
    self::assertSame('Fire safety questions', $result->thread->title);
    self::assertNull($result->thread->model);
  }

  #[Test]
  public function testInvokePersistsAnAllowedTenantSelectedModel(): void
  {
    $threads = $this->createMock(AssistantThreadRepositoryPort::class);
    $threads->expects(self::once())->method('save')->with(self::isInstanceOf(AssistantThread::class));

    $modelPolicy = new AssistantModelPolicy(['llama3', 'mistral']);

    $result = $this->handler(threads: $threads, modelPolicy: $modelPolicy)(
      new StartAssistantThreadCommand(self::ORG_ID, self::USER_ID, null, 'llama3'),
    );

    self::assertSame('llama3', $result->thread->model);
  }

  #[Test]
  public function testInvokeRejectsAModelOutsideTheAllowlist(): void
  {
    // An empty allowlist (the operator has not configured one) denies every
    // tenant-supplied model rather than permitting any.
    $modelPolicy = new AssistantModelPolicy([]);

    $this->expectException(AssistantValidationException::class);

    $this->handler(modelPolicy: $modelPolicy)(
      new StartAssistantThreadCommand(self::ORG_ID, self::USER_ID, null, 'not-an-allowed-model'),
    );
  }

  private function handler(
    ?AssistantThreadRepositoryPort $threads = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?AssistantModelPolicy $modelPolicy = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): StartAssistantThreadHandler {
    $threads ??= $this->createStub(AssistantThreadRepositoryPort::class);
    $authorization ??= $this->createStub(OrganizationAuthorizationPort::class);
    $modelPolicy ??= new AssistantModelPolicy([]);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);

    $uuidGenerator = $this->createStub(UuidGeneratorPort::class);
    $uuidGenerator->method('generate')->willReturn(self::THREAD_ID);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-20T00:00:00+00:00'));

    return new StartAssistantThreadHandler(
      threads: $threads,
      authorization: $authorization,
      modelPolicy: $modelPolicy,
      uuidFactory: new UuidFactory($uuidGenerator),
      eventDispatcher: $eventDispatcher,
      clock: $clock,
    );
  }
}
