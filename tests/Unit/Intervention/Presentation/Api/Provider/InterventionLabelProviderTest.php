<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Label\{InterventionLabelPage, InterventionLabelView};
use Intervention\Application\UseCase\Query\Label\ListInterventionLabels\{ListInterventionLabelsQuery, ListInterventionLabelsResult};
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Intervention\Presentation\Api\Factory\InterventionLabelOutputFactory;
use Intervention\Presentation\Api\Provider\InterventionLabelProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function iterator_to_array;

/**
 * Test InterventionLabelProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionLabelProvider::class)]
final class InterventionLabelProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string LABEL_ID = '550e8400-e29b-41d4-a716-446655441600';

  // #region Methods
  #[Test]
  public function testProvideListsLabelsAndClampsThePagination(): void
  {
    $requestStack = $this->requestStack('?organization=/api/organizations/' . self::ORG_ID . '&page=0&itemsPerPage=500');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInterventionLabelsQuery $query): bool {
        self::assertSame(self::USER_ID, $query->userId);
        self::assertSame(self::ORG_ID, $query->organizationId);
        self::assertSame(1, $query->page);
        self::assertSame(100, $query->itemsPerPage);

        return true;
      }))
      ->willReturn(new ListInterventionLabelsResult(new InterventionLabelPage([$this->view()], 1, 100, 1)));

    $paginator = new InterventionLabelProvider($queryBus, new InterventionLabelOutputFactory(), $this->security(), $requestStack)
      ->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $paginator);

    $items = iterator_to_array($paginator);
    self::assertCount(1, $items);
    self::assertSame(self::LABEL_ID, $items[0]->id);
  }

  #[Test]
  public function testProvideRequiresTheOrganizationFilter(): void
  {
    $provider = new InterventionLabelProvider(
      $this->createStub(QueryBusPort::class),
      new InterventionLabelOutputFactory(),
      $this->security(),
      $this->requestStack(''),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new InterventionLabelProvider(
      $this->createStub(QueryBusPort::class),
      new InterventionLabelOutputFactory(),
      $security,
      $this->requestStack('?organization=/api/organizations/' . self::ORG_ID),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsADomainFailureToItsHttpEquivalent(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InterventionAccessDeniedException('Not a member.'));

    $provider = new InterventionLabelProvider(
      $queryBus,
      new InterventionLabelOutputFactory(),
      $this->security(),
      $this->requestStack('?organization=/api/organizations/' . self::ORG_ID),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  private function requestStack(string $queryString): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-labels' . $queryString));

    return $requestStack;
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

  private function view(): InterventionLabelView
  {
    return new InterventionLabelView(
      self::LABEL_ID,
      self::ORG_ID,
      'Urgent',
      '#3b82f6',
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
