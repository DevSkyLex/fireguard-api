<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Infrastructure\EventSubscriber\{
  ExportAuditEventSubscriber,
  GovernanceAuditEventSubscriber,
  InterventionAuditEventSubscriber,
  OperationsAuditEventSubscriber,
  OrganizationAccessAuditEventSubscriber,
  OrganizationLifecycleAuditEventSubscriber,
  ResourceAuditEventSubscriber,
  SecurityAuditEventSubscriber
};
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use LogicException;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{DurableEventContextPort, IdempotentConsumerPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Instantiates the same audit event families as the service container. */
final class AuditSubscriberSet
{
  /**
   * @var list<class-string<EventSubscriberInterface>>
   */
  private const array SUBSCRIBERS = [
    SecurityAuditEventSubscriber::class,
    OrganizationLifecycleAuditEventSubscriber::class,
    OrganizationAccessAuditEventSubscriber::class,
    ResourceAuditEventSubscriber::class,
    InterventionAuditEventSubscriber::class,
    OperationsAuditEventSubscriber::class,
    GovernanceAuditEventSubscriber::class,
    ExportAuditEventSubscriber::class,
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
    $subscribers = [];
    foreach (self::SUBSCRIBERS as $class) {
      $subscribers[] = new $class(
        commandBus: $commandBus,
        sanitizer: $sanitizer,
        requestStack: $requestStack,
        security: $security,
        logger: $logger,
        eventContext: $eventContext,
        eventConsumer: $eventConsumer,
      );
    }

    return $subscribers;
  }

  /**
   * @return array<string, string>
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
