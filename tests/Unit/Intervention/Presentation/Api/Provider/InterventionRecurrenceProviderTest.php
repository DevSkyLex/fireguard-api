<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Get, GetCollection};
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\{InterventionRecurrencePage, InterventionRecurrenceView};
use Intervention\Application\UseCase\Query\Recurrence\GetInterventionRecurrence\{GetInterventionRecurrenceQuery, GetInterventionRecurrenceResult};
use Intervention\Application\UseCase\Query\Recurrence\ListInterventionRecurrences\{ListInterventionRecurrencesQuery, ListInterventionRecurrencesResult};
use Intervention\Presentation\Api\Dto\Output\InterventionRecurrenceOutput;
use Intervention\Presentation\Api\Factory\InterventionRecurrenceOutputFactory;
use Intervention\Presentation\Api\Provider\InterventionRecurrenceProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Test InterventionRecurrenceProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceProvider::class)]
final class InterventionRecurrenceProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RECURRENCE_ID = '550e8400-e29b-41d4-a716-446655441400';

  #[Test]
  public function testProvideReturnsTheRecurrenceForAnItemRequest(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetInterventionRecurrenceQuery $query): bool => self::USER_ID === $query->userId
        && self::RECURRENCE_ID === $query->recurrenceId))
      ->willReturn(new GetInterventionRecurrenceResult($this->view()));

    $provider = new InterventionRecurrenceProvider($queryBus, new InterventionRecurrenceOutputFactory(), $this->security(), new RequestStack());

    $output = $provider->provide(new Get(), ['id' => self::RECURRENCE_ID]);

    self::assertInstanceOf(InterventionRecurrenceOutput::class, $output);
    self::assertSame(self::RECURRENCE_ID, $output->id);
  }

  #[Test]
  public function testProvideRequiresTheOrganizationFilterForACollectionRequest(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-recurrences'));

    $provider = new InterventionRecurrenceProvider(
      $this->createStub(QueryBusPort::class),
      new InterventionRecurrenceOutputFactory(),
      $this->security(),
      $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideListsRecurrencesAndParsesTheIsActiveFilter(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-recurrences?organization=' . self::ORG_ID . '&isActive=false'));

    $page = new InterventionRecurrencePage([$this->view()], 1, 30, 1);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListInterventionRecurrencesQuery $query): bool => self::ORG_ID === $query->organizationId
        && false === $query->isActive))
      ->willReturn(new ListInterventionRecurrencesResult($page));

    $provider = new InterventionRecurrenceProvider($queryBus, new InterventionRecurrenceOutputFactory(), $this->security(), $requestStack);

    $result = $provider->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $result);
  }

  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function view(): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      self::RECURRENCE_ID,
      self::ORG_ID,
      '550e8400-e29b-41d4-a716-446655440002',
      'Quarterly audit',
      null,
      null,
      'quarterly',
      1,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      'UTC',
      7,
      new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      null,
      true,
      null,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
