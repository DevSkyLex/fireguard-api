<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\Application\UseCase;

use Customer\Application\Port\Outbound\CustomerRepositoryPort;
use Customer\Application\Service\CustomerAccessGuard;
use Customer\Application\UseCase\Command\ChangeCustomer\{ChangeCustomerCommand, ChangeCustomerHandler};
use Customer\Application\UseCase\Command\CreateCustomer\{CreateCustomerCommand, CreateCustomerHandler};
use Customer\Application\UseCase\Query\GetCustomer\{GetCustomerHandler, GetCustomerQuery};
use Customer\Application\UseCase\Query\ListCustomers\{ListCustomersHandler, ListCustomersQuery};
use Customer\Domain\Exception\CustomerException;
use Customer\Domain\Model\Customer\Customer;
use DateTimeImmutable;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};

/** Class CustomerHandlersTest. Authorized writes, scoped refusals, idempotent archives and bounded searches. @category Test */
final class CustomerHandlersTest extends TestCase
{
  private const string ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORG = '550e8400-e29b-41d4-a716-446655440002';

  private const string ACTOR = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function createsCustomerWithScopedIdentity(): void
  {
    $now = new DateTimeImmutable('2026-10-06T12:00:00Z');
    $repository = $this->createMock(CustomerRepositoryPort::class);
    $repository->expects(self::once())->method('save')->with(self::callback(static fn (Customer $customer): bool => self::ORG === $customer->organizationId && 'Owner' === $customer->name));
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::once())->method('generate')->willReturn(self::ID);
    $clock = $this->createMock(ClockPort::class);
    $clock->expects(self::once())->method('now')->willReturn($now);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (\Customer\Domain\Event\CustomerChangedEvent $event): bool => 'created' === $event->change && self::ID === $event->customerId));
    $result = new CreateCustomerHandler($repository, $this->access(OrganizationAccessDecision::GRANTED), $clock, $ids, $this->transactions(), $events)(new CreateCustomerCommand(self::ACTOR, self::ORG, 'Owner'));
    self::assertSame(self::ID, $result->customer->id);
    self::assertSame(1, $result->customer->revision);
  }

  #[Test]
  public function deniesUnentitledMemberBeforeReadingCustomers(): void
  {
    $repository = $this->createMock(CustomerRepositoryPort::class);
    $repository->expects(self::never())->method('find');
    $this->expectException(CustomerException::class);
    $this->expectExceptionMessage('Missing customer permission.');
    new GetCustomerHandler($repository, $this->access(OrganizationAccessDecision::MISSING_PERMISSION))(new GetCustomerQuery(self::ACTOR, self::ORG, self::ID));
  }

  #[Test]
  public function hidesOutsideOrganizationBeforeReadingCustomers(): void
  {
    $repository = $this->createMock(CustomerRepositoryPort::class);
    $repository->expects(self::never())->method('find');
    $this->expectException(CustomerException::class);
    $this->expectExceptionMessage('Customer not found.');
    new GetCustomerHandler($repository, $this->access(OrganizationAccessDecision::OUTSIDE_SCOPE))(new GetCustomerQuery(self::ACTOR, self::ORG, self::ID));
  }

  #[Test]
  public function archivalReplayDoesNotWriteAgain(): void
  {
    $now = new DateTimeImmutable();
    $customer = Customer::create(self::ID, self::ORG, 'Owner', null, null, null, [], $now)->archive($now);
    $repository = $this->createMock(CustomerRepositoryPort::class);
    $repository->expects(self::once())->method('find')->with(self::ID, self::ORG, true)->willReturn($customer);
    $repository->expects(self::never())->method('save');
    $clock = $this->createMock(ClockPort::class);
    $clock->expects(self::once())->method('now')->willReturn($now);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $handler = new ChangeCustomerHandler($repository, $this->access(OrganizationAccessDecision::GRANTED), $clock, $this->transactions(), $events);
    $result = $handler(new ChangeCustomerCommand(self::ACTOR, self::ORG, self::ID, 'archive', 2));
    self::assertSame(2, $result->customer->revision);
  }

  #[Test]
  public function staleRevisionNeverWrites(): void
  {
    $now = new DateTimeImmutable();
    $customer = Customer::create(self::ID, self::ORG, 'Owner', null, null, null, [], $now);
    $repository = $this->createMock(CustomerRepositoryPort::class);
    $repository->method('find')->willReturn($customer);
    $repository->expects(self::never())->method('save');
    $clock = $this->createMock(ClockPort::class);
    $clock->expects(self::never())->method('now');
    $this->expectException(CustomerException::class);
    $this->expectExceptionMessage('revision is stale');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    new ChangeCustomerHandler($repository, $this->access(OrganizationAccessDecision::GRANTED), $clock, $this->transactions(), $events)(new ChangeCustomerCommand(self::ACTOR, self::ORG, self::ID, 'patch', 0, ['name' => 'New']));
  }

  #[Test]
  public function listAndTotalUseIdenticalScopeAndFilters(): void
  {
    $repository = $this->createMock(CustomerRepositoryPort::class);
    $repository->expects(self::once())->method('list')->with(self::ORG, 'Owner', true, 10, 10)->willReturn([]);
    $repository->expects(self::once())->method('count')->with(self::ORG, 'Owner', true)->willReturn(15);
    $result = new ListCustomersHandler($repository, $this->access(OrganizationAccessDecision::GRANTED))(new ListCustomersQuery(self::ACTOR, self::ORG, ' Owner ', true, 2, 10));
    self::assertSame(15, $result->total);
    self::assertSame(2, $result->page);
  }

  private function access(OrganizationAccessDecision $decision): CustomerAccessGuard
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with(self::ACTOR, self::ORG, self::isString())->willReturn($decision);

    return new CustomerAccessGuard($authorization);
  }

  private function transactions(): TransactionManagerPort
  {
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transactions;
  }
}
