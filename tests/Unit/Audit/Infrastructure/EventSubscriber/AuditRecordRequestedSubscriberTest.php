<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\Contract\AuditRecordPayload;
use Audit\Application\Contract\Event\AuditRecordRequestedEvent;
use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditRecordRequestedSubscriber;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;

use function get_object_vars;

/**
 * Class AuditRecordRequestedSubscriberTest
 *
 * Verifies immutable payload forwarding, receipt identity and retry failure propagation.
 *
 * @category Unit Test
 */
final class AuditRecordRequestedSubscriberTest extends TestCase
{
  // #region Methods
  /**
   * Method subscribesToTheGenericAuditRequest
   *
   * Keeps the durable consumer tied to its stable event name.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function subscribesToTheGenericAuditRequest(): void
  {
    self::assertSame(['audit.audit_record_requested_event' => 'onRecordRequested'], AuditRecordRequestedSubscriber::getSubscribedEvents());
  }

  /**
   * Method forwardsEverySanitizedFieldWithinTheActionAndSubjectReceipt
   *
   * Preserves the enqueue snapshot and historical receipt key without reapplying ambient context.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function forwardsEverySanitizedFieldWithinTheActionAndSubjectReceipt(): void
  {
    $event = $this->event();
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static function (CommandMessage $command) use ($event): bool {
      self::assertInstanceOf(RecordAuditEventCommand::class, $command);
      self::assertSame(get_object_vars($event->record), get_object_vars($command));

      return true;
    }))->willReturn(new RecordAuditEventResult('ledger-id'));
    $consumer = $this->createMock(IdempotentConsumerPort::class);
    $consumer->expects(self::once())->method('consume')->with('original-request', 'audit:facility.archived:facility:facility-id', self::isCallable())
      ->willReturnCallback(static function (string $requestId, string $key, callable $operation): bool {
        $operation();

        return true;
      });

    new AuditRecordRequestedSubscriber($commands, $consumer)->onRecordRequested($event);
  }

  /**
   * Method skipsTheLedgerCommandWhenTheReceiptAlreadyExists
   *
   * Leaves duplicate-delivery suppression to the local atomic consumer.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function skipsTheLedgerCommandWhenTheReceiptAlreadyExists(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $consumer = $this->createMock(IdempotentConsumerPort::class);
    $consumer->expects(self::once())->method('consume')->with('original-request', 'audit:facility.archived:facility:facility-id', self::isCallable())->willReturn(false);

    new AuditRecordRequestedSubscriber($commands, $consumer)->onRecordRequested($this->event());
  }

  /**
   * Method propagatesALedgerFailureForDurableRetry
   *
   * Prevents a failed append from being acknowledged as a consumed request.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function propagatesALedgerFailureForDurableRetry(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->willThrowException(new RuntimeException('ledger unavailable'));
    $consumer = $this->createMock(IdempotentConsumerPort::class);
    $consumer->expects(self::once())->method('consume')->willReturnCallback(static function (string $requestId, string $key, callable $operation): bool {
      $operation();

      return true;
    });
    $subscriber = new AuditRecordRequestedSubscriber($commands, $consumer);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('ledger unavailable');
    $subscriber->onRecordRequested($this->event());
  }

  /**
   * Method event
   *
   * Supplies an already sanitized request with every ledger field populated.
   *
   * @access private
   *
   * @return AuditRecordRequestedEvent durable snapshot
   */
  private function event(): AuditRecordRequestedEvent
  {
    return new AuditRecordRequestedEvent('original-request', new AuditRecordPayload(
      action: 'facility.archived',
      actorType: 'user',
      actorId: 'actor-id',
      actorEmail: null,
      actorEmailHash: 'actor-email-hash',
      subjectType: 'facility',
      subjectId: 'facility-id',
      clientId: 'client-id',
      tenantId: 'tenant-id',
      organizationId: 'organization-id',
      ipAddress: null,
      ipHash: 'ip-hash',
      userAgent: 'original-agent',
      metadata: ['reason' => 'archive', 'organization_id' => 'organization-id'],
      occurredAt: new DateTimeImmutable('2026-10-11T08:00:00+00:00'),
    ));
  }
  // #endregion
}
