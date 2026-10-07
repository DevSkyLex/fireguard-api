<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\Presentation\Api;

use ApiPlatform\Metadata\{GetCollection, Patch, Post};
use Customer\Application\Contract\CustomerView;
use Customer\Application\UseCase\Command\ChangeCustomer\{ChangeCustomerCommand, ChangeCustomerResult};
use Customer\Application\UseCase\Command\CreateCustomer\{CreateCustomerCommand, CreateCustomerResult};
use Customer\Application\UseCase\Query\ListCustomers\{ListCustomersQuery, ListCustomersResult};
use Customer\Domain\Model\Customer\Customer;
use Customer\Presentation\Api\Dto\Input\{ChangeCustomerInput, CreateCustomerInput};
use Customer\Presentation\Api\Operation\CustomerOperations;
use Customer\Presentation\Api\Processor\CustomerProcessor;
use Customer\Presentation\Api\Provider\CustomerProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

/** Class CustomerTransportTest. HTTP translation keeps revisions and patch presence exact. @category Test */
final class CustomerTransportTest extends TestCase
{
  private const string ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORG = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function creationTranslatesTypedInputIntoCommand(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (CreateCustomerCommand $command): bool => self::ORG === $command->organizationId && 'Owner' === $command->name))->willReturn(new CreateCustomerResult($this->view()));
    $input = new CreateCustomerInput();
    $input->name = 'Owner';
    $output = new CustomerProcessor($commands, $this->actor(), new RequestStack())->process($input, new Post(name: CustomerOperations::CREATE), ['organizationId' => self::ORG]);
    self::assertSame(self::ID, $output->id);
    self::assertSame(1, $output->revision);
  }

  #[Test]
  public function patchOnlyIncludesSuppliedFieldsAndParsesQuotedRevision(): void
  {
    $requests = new RequestStack();
    $request = Request::create('/', 'PATCH', content: '{"code":null}');
    $request->headers->set('If-Match', '"revision-1"');
    $requests->push($request);
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (ChangeCustomerCommand $command): bool => 1 === $command->expectedRevision && ['code' => null] === $command->changes && 'patch' === $command->action))->willReturn(new ChangeCustomerResult($this->view()));
    new CustomerProcessor($commands, $this->actor(), $requests)->process(new ChangeCustomerInput(), new Patch(name: CustomerOperations::PATCH), ['organizationId' => self::ORG, 'id' => self::ID]);
  }

  #[Test]
  public function collectionCarriesSearchArchivalAndPagination(): void
  {
    $requests = new RequestStack();
    $requests->push(Request::create('/?search=Owner&archived=true&page=2&itemsPerPage=10'));
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ListCustomersQuery $query): bool => 'Owner' === $query->search && $query->archived && 2 === $query->page && 10 === $query->itemsPerPage))->willReturn(new ListCustomersResult([], 12, 2, 10));
    $result = new CustomerProvider($queries, $this->actor(), $requests)->provide(new GetCollection(name: CustomerOperations::LIST), ['organizationId' => self::ORG]);
    self::assertInstanceOf(\ApiPlatform\State\Pagination\TraversablePaginator::class, $result);
    self::assertSame(12.0, $result->getTotalItems());
  }

  private function view(): CustomerView
  {
    return CustomerView::fromCustomer(Customer::create(self::ID, self::ORG, 'Owner', null, null, null, [], new DateTimeImmutable()));
  }

  private function actor(): CurrentActorPort
  {
    $actor = $this->createMock(CurrentActorPort::class);
    $actor->expects(self::once())->method('userId')->willReturn(self::ID);

    return $actor;
  }
}
