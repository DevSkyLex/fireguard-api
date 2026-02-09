<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Infrastructure\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tenant\Application\Port\Inbound\TenantResolverPort;
use Tenant\Infrastructure\EventSubscriber\TenantIsolationSubscriber;
use Tenant\Infrastructure\Persistence\Doctrine\Filter\TenantFilter;

/**
 * Test TenantIsolationSubscriberTest.
 *
 * @category Event Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TenantIsolationSubscriber::class)]
final class TenantIsolationSubscriberTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testDisablesFilterWhenNoTenantId(): void
  {
    $tenantResolver = $this->createMock(TenantResolverPort::class);
    $tenantResolver->method('resolveTenantId')->willReturn(null);

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(true);
    $filters->expects(self::once())
      ->method('disable')
      ->with('tenant');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getFilters')->willReturn($filters);

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $tenantResolver,
      entityManager: $entityManager,
    );

    $event = $this->createRequestEvent();
    $subscriber->onKernelRequest($event);
  }

  #[Test]
  public function testEnablesFilterWhenTenantIdProvided(): void
  {
    $tenantResolver = $this->createMock(TenantResolverPort::class);
    $tenantResolver->method('resolveTenantId')->willReturn('tenant-123');

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(false);
    $filters->method('setFiltersStateDirty');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getFilters')->willReturn($filters);

    $filter = new TenantFilter($entityManager);
    $filters->expects(self::once())
      ->method('enable')
      ->with('tenant')
      ->willReturn($filter);

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $tenantResolver,
      entityManager: $entityManager,
    );

    $event = $this->createRequestEvent();
    $subscriber->onKernelRequest($event);

    self::assertTrue($filter->hasParameter('tenant_id'));
  }

  #[Test]
  public function testUsesExistingFilterWhenAlreadyEnabled(): void
  {
    $tenantResolver = $this->createMock(TenantResolverPort::class);
    $tenantResolver->method('resolveTenantId')->willReturn('tenant-456');

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(true);
    $filters->method('setFiltersStateDirty');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getFilters')->willReturn($filters);

    $filter = new TenantFilter($entityManager);
    $filters->expects(self::once())
      ->method('getFilter')
      ->with('tenant')
      ->willReturn($filter);

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $tenantResolver,
      entityManager: $entityManager,
    );

    $event = $this->createRequestEvent();
    $subscriber->onKernelRequest($event);

    self::assertTrue($filter->hasParameter('tenant_id'));
  }

  private function createRequestEvent(): RequestEvent
  {
    $kernel = $this->createMock(HttpKernelInterface::class);

    return new RequestEvent(
      $kernel,
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
    );
  }
  // #endregion
}
