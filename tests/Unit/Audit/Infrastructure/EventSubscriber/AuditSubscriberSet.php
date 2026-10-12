<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Contract\Event\AuditRecordRequestedEvent;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Audit\Infrastructure\EventSubscriber\AuditRecordRequestedSubscriber;
use Audit\Infrastructure\Service\{AuditActorResolver, AuditPiiSanitizer, AuditRecordPayloadFactory};
use LogicException;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{DurableEventContextPort, IdempotentConsumerPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Composes owner audit projections with the real Audit payload factory and request consumer. */
final class AuditSubscriberSet
{
  /**
   * @var list<class-string<EventSubscriberInterface>>
   */
  private const array SUBSCRIBERS = [
    \Approval\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Audit\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Auth\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Automation\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Calendar\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Compliance\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Equipment\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Facility\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Import\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Inspection\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Intervention\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Maintenance\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Messaging\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \OAuth\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Organization\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Otp\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \User\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
    \Webhook\Infrastructure\Adapter\Audit\AuditRecordEventAdapter::class,
  ];

  /**
   * @return list<EventSubscriberInterface>
   */
  public static function create(
    CommandBusPort $commandBus,
    AuditPiiSanitizer $sanitizer,
    RequestStack $requestStack,
    Security $security,
    LoggerInterface $logger,
    DurableEventContextPort $eventContext,
    IdempotentConsumerPort $eventConsumer,
  ): array {
    $factory = new AuditRecordPayloadFactory($sanitizer, new AuditActorResolver($security, $eventContext), $requestStack);
    $consumer = new AuditRecordRequestedSubscriber($commandBus, $eventConsumer);
    $recording = new class ($factory, $consumer, $eventContext) implements AuditRecordingPort {
      private int $sequence = 0;

      public function __construct(private AuditRecordPayloadFactory $factory, private AuditRecordRequestedSubscriber $consumer, private DurableEventContextPort $context)
      {
      }

      public function requestRecord(AuditRecordRequest $request): void
      {
        $this->consumer->onRecordRequested(new AuditRecordRequestedEvent(
          $this->context->eventId() ?? 'test-request-' . ++$this->sequence,
          $this->factory->create($request),
        ));
      }
    };
    $subscribers = [];
    foreach (self::SUBSCRIBERS as $class) {
      $subscribers[] = new $class($recording);
    }

    return $subscribers;
  }

  /**
   * @return AuditSubscriberFamily owner projections accessed by their historical listener names
   */
  public static function family(
    CommandBusPort $commandBus,
    AuditPiiSanitizer $sanitizer,
    RequestStack $requestStack,
    Security $security,
    LoggerInterface $logger,
    DurableEventContextPort $eventContext,
    IdempotentConsumerPort $eventConsumer,
  ): AuditSubscriberFamily {
    return new AuditSubscriberFamily(self::create($commandBus, $sanitizer, $requestStack, $security, $logger, $eventContext, $eventConsumer));
  }

  /**
   * @return array<string, string> unchanged owner event mappings
   */
  public static function subscribedEvents(): array
  {
    $events = [];
    foreach (self::SUBSCRIBERS as $class) {
      foreach ($class::getSubscribedEvents() as $event => $method) {
        if (isset($events[$event])) {
          throw new LogicException('Duplicate audit subscription: ' . $event);
        }
        $events[$event] = $method;
      }
    }

    return $events;
  }
}
