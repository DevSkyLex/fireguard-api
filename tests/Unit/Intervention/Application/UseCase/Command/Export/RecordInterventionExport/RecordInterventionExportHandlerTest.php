<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Export\RecordInterventionExport;

use Intervention\Application\Contract\Event\Export\InterventionsExportedEvent;
use Intervention\Application\UseCase\Command\Export\RecordInterventionExport\{RecordInterventionExportCommand, RecordInterventionExportHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Throwable;

#[CoversClass(RecordInterventionExportHandler::class)]
final class RecordInterventionExportHandlerTest extends TestCase
{
  /**
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('refusals')]
  public function noExportIntentCanBeRecordedOutsideAuthorizedScope(OrganizationAccessDecision $decision, string $exception): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', 'org', 'organization.interventions.read')->willReturn($decision);
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::never())->method('transactional');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException($exception);
    new RecordInterventionExportHandler($transactions, $events, $authorization)(new RecordInterventionExportCommand(new InterventionsExportedEvent('org', 'actor', 'csv', 1, [])));
  }

  /**
   * @return iterable<string, array{OrganizationAccessDecision, class-string<Throwable>}>
   */
  public static function refusals(): iterable
  {
    yield 'scope' => [OrganizationAccessDecision::OUTSIDE_SCOPE, InterventionNotFoundException::class];
    yield 'permission' => [OrganizationAccessDecision::MISSING_PERMISSION, InterventionAccessDeniedException::class];
  }

  #[Test]
  public function recordsIntentInsideTheOwningTransaction(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $active = new class () {
      public bool $value = false;
    };
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static function (callable $operation) use ($active): mixed {
      $active->value = true;

      try {
        return $operation();
      } finally {
        $active->value = false;
      }
    });
    $event = new InterventionsExportedEvent('org', 'actor', 'csv', 1, []);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with($event)->willReturnCallback(static function () use ($active): void { self::assertTrue($active->value); });
    new RecordInterventionExportHandler($transactions, $events, $authorization)(new RecordInterventionExportCommand($event));
    self::assertFalse($active->value);
  }
}
