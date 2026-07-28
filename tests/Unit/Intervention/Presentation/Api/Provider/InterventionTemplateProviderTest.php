<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Get, GetCollection};
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Template\{InterventionTemplatePage, InterventionTemplateView};
use Intervention\Application\UseCase\Query\Template\GetInterventionTemplate\{GetInterventionTemplateQuery, GetInterventionTemplateResult};
use Intervention\Application\UseCase\Query\Template\ListInterventionTemplates\{ListInterventionTemplatesQuery, ListInterventionTemplatesResult};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Presentation\Api\Dto\Output\InterventionTemplateOutput;
use Intervention\Presentation\Api\Factory\InterventionTemplateOutputFactory;
use Intervention\Presentation\Api\Provider\InterventionTemplateProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test InterventionTemplateProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionTemplateProvider::class)]
final class InterventionTemplateProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string TEMPLATE_ID = '550e8400-e29b-41d4-a716-446655441700';

  // #region Methods
  #[Test]
  public function testProvideReturnsASingleTemplateForAnItemRequest(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetInterventionTemplateQuery $query): bool => self::USER_ID === $query->userId
        && self::TEMPLATE_ID === $query->templateId))
      ->willReturn(new GetInterventionTemplateResult($this->view()));

    $output = $this->provider($queryBus, $this->requestStack(''))->provide(new Get(), ['id' => self::TEMPLATE_ID]);

    self::assertInstanceOf(InterventionTemplateOutput::class, $output);
    self::assertSame(self::TEMPLATE_ID, $output->id);
  }

  #[Test]
  public function testProvideForwardsTheSearchFilterOnACollectionRequest(): void
  {
    $requestStack = $this->requestStack('?organization=/api/organizations/' . self::ORG_ID . '&search=audit');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListInterventionTemplatesQuery $query): bool {
        self::assertSame(self::ORG_ID, $query->organizationId);
        self::assertSame('audit', $query->search);
        self::assertSame(1, $query->page);
        self::assertSame(30, $query->itemsPerPage);

        return true;
      }))
      ->willReturn(new ListInterventionTemplatesResult(new InterventionTemplatePage([$this->view()], 1, 30, 1)));

    $paginator = $this->provider($queryBus, $requestStack)->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
  }

  #[Test]
  public function testProvideOmitsAnEmptySearchFilter(): void
  {
    $requestStack = $this->requestStack('?organization=/api/organizations/' . self::ORG_ID . '&search=');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListInterventionTemplatesQuery $query): bool => null === $query->search))
      ->willReturn(new ListInterventionTemplatesResult(new InterventionTemplatePage([], 1, 30, 0)));

    $this->provider($queryBus, $requestStack)->provide(new GetCollection());
  }

  #[Test]
  public function testProvideRequiresTheOrganizationFilter(): void
  {
    $provider = $this->provider($this->createStub(QueryBusPort::class), $this->requestStack(''));

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new InterventionTemplateProvider(
      $this->createStub(QueryBusPort::class),
      new InterventionTemplateOutputFactory(),
      $security,
      $this->requestStack(''),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => self::TEMPLATE_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureOnAnItemRequest(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::TEMPLATE_ID));

    $provider = $this->provider($queryBus, $this->requestStack(''));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => self::TEMPLATE_ID]);
  }

  #[Test]
  public function testProvideMapsADomainFailureOnACollectionRequest(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::ORG_ID));

    $provider = $this->provider($queryBus, $this->requestStack('?organization=/api/organizations/' . self::ORG_ID));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection());
  }

  private function provider(QueryBusPort $queryBus, RequestStack $requestStack): InterventionTemplateProvider
  {
    return new InterventionTemplateProvider(
      $queryBus,
      new InterventionTemplateOutputFactory(),
      $this->security(),
      $requestStack,
    );
  }

  private function requestStack(string $queryString): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-templates' . $queryString));

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

  private function view(): InterventionTemplateView
  {
    return new InterventionTemplateView(
      self::TEMPLATE_ID,
      self::ORG_ID,
      'Quarterly audit',
      null,
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
