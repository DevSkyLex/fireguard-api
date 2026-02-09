<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tenant\Application\Port\Inbound\TenantResolverPort;

/**
 * Subscriber TenantIsolationSubscriber.
 *
 * Enables the tenant Doctrine filter
 * based on the current request context.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantIsolationSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param TenantResolverPort $tenantResolver the tenant resolver
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private TenantResolverPort $tenantResolver,
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  /**
   * Method getSubscribedEvents.
   *
   * Declares subscribed kernel events.
   *
   * @since 1.0.0
   *
   * @return array<string, string> event map
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::REQUEST => 'onKernelRequest',
    ];
  }

  /**
   * Method onKernelRequest.
   *
   * Enables or disables tenant isolation
   * based on the resolved tenant ID.
   *
   * @since 1.0.0
   *
   * @param RequestEvent $event the current request event
   */
  public function onKernelRequest(RequestEvent $event): void
  {
    if (!$event->isMainRequest()) {
      return;
    }

    $tenantId = $this->tenantResolver->resolveTenantId();
    $filters = $this->entityManager->getFilters();

    if (null === $tenantId) {
      if ($filters->isEnabled('tenant')) {
        $filters->disable('tenant');
      }

      return;
    }

    try {
      $filter = $filters->isEnabled('tenant')
        ? $filters->getFilter('tenant')
        : $filters->enable('tenant');

      $filter->setParameter('tenant_id', $tenantId);
    } catch (InvalidArgumentException) {
      // Filter not configured - ignore.
    }
  }
}
