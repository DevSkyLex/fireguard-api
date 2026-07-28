<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Infrastructure\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Event\{RequestEvent, TerminateEvent};
use Symfony\Component\HttpKernel\{HttpKernelInterface, KernelEvents};
use Symfony\Contracts\Service\ResetInterface;
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
    $tenantResolver = $this->createStub(TenantResolverPort::class);
    $tenantResolver->method('resolveTenantId')->willReturn(null);

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(true);
    $filters->expects(self::once())
      ->method('disable')
      ->with('tenant');

    $entityManager = $this->createStub(EntityManagerInterface::class);
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
    $tenantResolver = $this->createStub(TenantResolverPort::class);
    $tenantResolver->method('resolveTenantId')->willReturn('tenant-123');

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(false);
    $filters->method('setFiltersStateDirty');

    $entityManager = $this->createStub(EntityManagerInterface::class);
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
    $tenantResolver = $this->createStub(TenantResolverPort::class);
    $tenantResolver->method('resolveTenantId')->willReturn('tenant-456');

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(true);
    $filters->method('setFiltersStateDirty');

    $entityManager = $this->createStub(EntityManagerInterface::class);
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

  #[Test]
  public function testDisablesFilterOnTerminate(): void
  {
    $tenantResolver = $this->createStub(TenantResolverPort::class);

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(true);
    $filters->expects(self::once())
      ->method('disable')
      ->with('tenant');

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getFilters')->willReturn($filters);

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $tenantResolver,
      entityManager: $entityManager,
    );

    $subscriber->onKernelTerminate($this->createTerminateEvent());
  }

  #[Test]
  public function testResetDisablesFilter(): void
  {
    $tenantResolver = $this->createStub(TenantResolverPort::class);

    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willReturn(true);
    $filters->expects(self::once())
      ->method('disable')
      ->with('tenant');

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getFilters')->willReturn($filters);

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $tenantResolver,
      entityManager: $entityManager,
    );

    self::assertInstanceOf(ResetInterface::class, $subscriber);

    $subscriber->reset();
  }

  #[Test]
  public function testItLeavesTheFilterAloneOnASubRequest(): void
  {
    // A sub-request must not re-resolve the tenant: the main request already
    // pinned it, and re-running would let an ESI/forward widen the scope.
    $tenantResolver = $this->createMock(TenantResolverPort::class);
    $tenantResolver->expects(self::never())->method('resolveTenantId');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('getFilters');

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $tenantResolver,
      entityManager: $entityManager,
    );

    $subscriber->onKernelRequest(new RequestEvent(
      $this->createStub(HttpKernelInterface::class),
      new Request(),
      HttpKernelInterface::SUB_REQUEST,
    ));
  }

  #[Test]
  public function testItSubscribesToTheRequestAndTerminateKernelEvents(): void
  {
    self::assertSame(
      [
        KernelEvents::REQUEST => 'onKernelRequest',
        KernelEvents::TERMINATE => 'onKernelTerminate',
      ],
      TenantIsolationSubscriber::getSubscribedEvents(),
    );
  }

  #[Test]
  public function testItIgnoresAnUnconfiguredFilterWhenEnabling(): void
  {
    $tenantResolver = $this->createStub(TenantResolverPort::class);
    $tenantResolver->method('resolveTenantId')->willReturn('tenant-789');

    $filters = $this->createMock(FilterCollection::class);
    $filters->method('isEnabled')->with('tenant')->willReturn(false);
    $filters->expects(self::once())
      ->method('enable')
      ->with('tenant')
      ->willThrowException(new InvalidArgumentException('Filter "tenant" does not exist.'));

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getFilters')->willReturn($filters);

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $tenantResolver,
      entityManager: $entityManager,
    );

    // The point of the test is that this call does NOT propagate the
    // InvalidArgumentException; the `enable` expectation is the assertion.
    $subscriber->onKernelRequest($this->createRequestEvent());
  }

  #[Test]
  public function testItIgnoresAnUnconfiguredFilterWhenDisabling(): void
  {
    $filters = $this->createMock(FilterCollection::class);
    $filters->expects(self::once())
      ->method('isEnabled')
      ->with('tenant')
      ->willThrowException(new InvalidArgumentException('Filter "tenant" does not exist.'));
    $filters->expects(self::never())->method('disable');

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getFilters')->willReturn($filters);

    $subscriber = new TenantIsolationSubscriber(
      tenantResolver: $this->createStub(TenantResolverPort::class),
      entityManager: $entityManager,
    );

    // Same contract on the teardown side: the `disable` never-expectation and
    // the absence of a propagated exception are the assertions.
    $subscriber->reset();
  }

  private function createRequestEvent(): RequestEvent
  {
    $kernel = $this->createStub(HttpKernelInterface::class);

    return new RequestEvent(
      $kernel,
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
    );
  }

  private function createTerminateEvent(): TerminateEvent
  {
    $kernel = $this->createStub(HttpKernelInterface::class);

    return new TerminateEvent(
      $kernel,
      new Request(),
      new Response(),
    );
  }
  // #endregion
}
