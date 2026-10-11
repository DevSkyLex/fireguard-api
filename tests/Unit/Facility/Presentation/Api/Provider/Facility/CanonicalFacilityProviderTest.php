<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\Contract\Facility\{CanonicalFacilityCollectionItem, CanonicalFacilityPage};
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityQuery, GetFacilityResult};
use Facility\Application\UseCase\Query\Facility\ListCanonicalFacilities\{ListCanonicalFacilitiesQuery, ListCanonicalFacilitiesResult};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityNotFoundException};
use Facility\Presentation\Api\Factory\FacilityDetailOutputFactory;
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function iterator_to_array;

/** Tests typed reads and preserves application refusals for centralized HTTP mapping. */
#[CoversClass(CanonicalFacilityProvider::class)]
final class CanonicalFacilityProviderTest extends TestCase
{
  #[Test]
  public function itemResolvesAuthorizedOwnerBeforeReadingTheCompleteSnapshot(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $calls = 0;
    $queries->expects(self::exactly(2))->method('ask')->willReturnCallback(function (object $query) use (&$calls): object {
      if (0 === $calls++) {
        self::assertInstanceOf(GetFacilityMediaContextQuery::class, $query);
        self::assertSame('user-id', $query->userId);
        self::assertSame('facility-id', $query->facilityId);
        self::assertFalse($query->write);

        return new GetFacilityMediaContextResult('organization-id', 'facility-id');
      }
      self::assertInstanceOf(GetFacilityQuery::class, $query);
      self::assertSame('organization-id', $query->organizationId);
      self::assertSame('facility-id', $query->facilityId);
      $now = new DateTimeImmutable('2026-09-20');

      return new GetFacilityResult('facility-id', 'organization-id', null, 'building', 'Building B', null, 'active', null, [], $now, $now, path: [['id' => 'root', 'name' => 'Root', 'type' => 'site']], revision: 7);
    });
    $output = $this->provider($queries)->provide(new Get(), ['id' => 'facility-id']);
    self::assertInstanceOf(\Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput::class, $output);
    self::assertSame('facility-id', $output->id);
    self::assertSame(7, $output->revision);
    self::assertSame([['id' => 'root', 'name' => 'Root', 'type' => 'site']], $output->path);
  }

  #[Test]
  public function collectionDispatchesOneTypedQueryAndMapsThePaginatedSnapshot(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (object $query): bool => $query instanceof ListCanonicalFacilitiesQuery && 'user-id' === $query->userId && 'organization-id' === $query->organizationId && 'intervention-id' === $query->interventionId && 'draft' === $query->recordStatus && 1 === $query->page && 100 === $query->itemsPerPage && $query->includePath))->willReturn(new ListCanonicalFacilitiesResult(new CanonicalFacilityPage([new CanonicalFacilityCollectionItem('facility-id', 'organization-id', 'intervention-id', 'draft', 4, null, true, 'building', 'Building', null, 'active', null, 48.8, 2.3, ['key' => 'value'], 2, 6.0, 3.0, 'customer-id', 'created', 'updated')], 15), 1, 100, ['facility-id' => [['id' => 'root', 'name' => 'Root', 'type' => 'site']]], ['facility-id' => ['parent_archived']]));
    $stack = new RequestStack();
    $stack->push(Request::create('/api/facilities', parameters: ['organization' => '/api/organizations/organization-id', 'intervention' => '/api/interventions/intervention-id', 'recordStatus' => 'draft', 'includePath' => 'true']));
    $page = $this->provider($queries, $stack)->provide(new GetCollection(), context: ['filters' => ['page' => -5, 'itemsPerPage' => 500]]);
    self::assertInstanceOf(\ApiPlatform\State\Pagination\TraversablePaginator::class, $page);
    $items = iterator_to_array($page);
    self::assertSame(15.0, $page->getTotalItems());
    self::assertCount(1, $items);
    self::assertInstanceOf(\Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput::class, $items[0]);
    self::assertSame(4, $items[0]->revision);
    self::assertSame('/api/interventions/intervention-id', $items[0]->intervention);
    self::assertSame(2, $items[0]->levelIndex);
    self::assertSame(['parent_archived'], $items[0]->hierarchyIssues);
  }

  #[Test]
  public function unauthenticatedCallerDoesNotReachTheApplication(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $this->expectException(AccessDeniedHttpException::class);
    new CanonicalFacilityProvider($security, new RequestStack(), new FacilityDetailOutputFactory(), $queries)->provide(new GetCollection());
  }

  #[Test]
  public function missingOwnerRefusalIsPreservedForTheCentralSubscriber(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::isInstanceOf(GetFacilityMediaContextQuery::class))->willThrowException(new FacilityNotFoundException('Facility not found.'));
    $this->expectException(FacilityNotFoundException::class);
    $this->provider($queries)->provide(new Get(), ['id' => 'missing']);
  }

  #[Test]
  public function deniedCollectionRefusalIsPreservedForTheCentralSubscriber(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::isInstanceOf(ListCanonicalFacilitiesQuery::class))->willThrowException(new FacilityAccessDeniedException('Missing organization.facilities.read permission.'));
    $this->expectException(FacilityAccessDeniedException::class);
    $this->provider($queries)->provide(new GetCollection());
  }

  private function provider(QueryBusPort $queries, ?RequestStack $stack = null): CanonicalFacilityProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true));

    return new CanonicalFacilityProvider($security, $stack ?? new RequestStack(), new FacilityDetailOutputFactory(), $queries);
  }
}
