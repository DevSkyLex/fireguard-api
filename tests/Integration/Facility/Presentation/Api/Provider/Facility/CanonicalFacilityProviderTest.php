<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function array_map;
use function array_values;
use function iterator_to_array;

/**
 * Test CanonicalFacilityProviderTest.
 *
 * Complements the unit suite, which stops at the guards: this one runs the
 * collection query through a live Doctrine QueryBuilder against PostgreSQL, so
 * the optional `intervention` filter arm is actually parsed and executed.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalFacilityProvider::class)]
final class CanonicalFacilityProviderTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'bb0e8400-e29b-41d4-a716-4466554d0001';

  private const string INTERVENTION_ID = 'bb0e8400-e29b-41d4-a716-4466554d0002';

  private const string OTHER_INTERVENTION_ID = 'bb0e8400-e29b-41d4-a716-4466554d0003';

  private const string DRAFT_FACILITY_ID = 'bb0e8400-e29b-41d4-a716-4466554d0010';

  private const string OTHER_DRAFT_FACILITY_ID = 'bb0e8400-e29b-41d4-a716-4466554d0011';

  private const string SECOND_DRAFT_FACILITY_ID = 'bb0e8400-e29b-41d4-a716-4466554d0012';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Canonical Facility Provider Test';
    $organization->slug = 'canonical-facility-provider-test';
    $organization->ownerUserId = 'bb0e8400-e29b-41d4-a716-4466554d9000';
    $organization->createdByUserId = 'bb0e8400-e29b-41d4-a716-4466554d9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $this->persistFacility(self::DRAFT_FACILITY_ID, $organization, 'Draft Site', self::INTERVENTION_ID);
    $this->persistFacility(self::OTHER_DRAFT_FACILITY_ID, $organization, 'Other Draft Site', self::OTHER_INTERVENTION_ID);

    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testProvideNarrowsTheCollectionToTheRequestedIntervention(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facilities?intervention=/api/interventions/' . self::INTERVENTION_ID));

    $result = $this->provider($requestStack)->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    $first = $items[0];
    self::assertInstanceOf(FacilityOutput::class, $first);
    self::assertSame(self::DRAFT_FACILITY_ID, $first->id);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $first->intervention);
    self::assertSame('draft', $first->recordStatus);
  }

  #[Test]
  public function testProvidePaginatesTheInterventionFilteredCollection(): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $this->persistFacility(
      self::SECOND_DRAFT_FACILITY_ID,
      $organization,
      'Second Draft Site',
      self::INTERVENTION_ID,
      new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/facilities?intervention=/api/interventions/' . self::INTERVENTION_ID));
    $provider = $this->provider($requestStack);

    $firstPage = $provider->provide(new GetCollection(), [], ['filters' => ['page' => '1', 'itemsPerPage' => '1']]);
    self::assertInstanceOf(TraversablePaginator::class, $firstPage);
    self::assertSame(2.0, $firstPage->getTotalItems());
    self::assertSame([self::DRAFT_FACILITY_ID], $this->identifiers($firstPage));

    $secondPage = $provider->provide(new GetCollection(), [], ['filters' => ['page' => '2', 'itemsPerPage' => '1']]);
    self::assertInstanceOf(TraversablePaginator::class, $secondPage);
    self::assertSame(2.0, $secondPage->getTotalItems());
    self::assertSame([self::SECOND_DRAFT_FACILITY_ID], $this->identifiers($secondPage));
  }

  /**
   * @param TraversablePaginator<FacilityOutput> $page
   *
   * @return list<string>
   */
  private function identifiers(TraversablePaginator $page): array
  {
    return array_map(
      static fn (FacilityOutput $output): string => (string) $output->id,
      array_values(iterator_to_array($page)),
    );
  }

  private function provider(RequestStack $requestStack): CanonicalFacilityProvider
  {
    $authorization = self::createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $security = self::createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('bb0e8400-e29b-41d4-a716-4466554d9000', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $resources = self::createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft'),
    );

    return new CanonicalFacilityProvider(
      $this->entityManager,
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($resources),
    );
  }

  private function persistFacility(
    string $id,
    OrganizationRecord $organization,
    string $name,
    string $interventionId,
    ?DateTimeImmutable $createdAt = null,
  ): void {
    $record = new FacilityRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->interventionId = $interventionId;
    $record->recordStatus = 'draft';
    $record->type = 'site';
    $record->name = $name;
    $record->status = 'active';
    $record->metadata = [];
    $record->createdAt = $createdAt ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM facilities WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
