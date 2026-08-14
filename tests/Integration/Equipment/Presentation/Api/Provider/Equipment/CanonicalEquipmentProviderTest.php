<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
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
 * Test CanonicalEquipmentProviderTest.
 *
 * Complements the unit suite, which stops at the guards: this one runs the
 * collection query through a live Doctrine QueryBuilder against PostgreSQL, so
 * the optional `intervention` and `facility` filter arms are actually parsed
 * and executed.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalEquipmentProvider::class)]
final class CanonicalEquipmentProviderTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'cc0e8400-e29b-41d4-a716-4466554f0001';

  private const string INTERVENTION_ID = 'cc0e8400-e29b-41d4-a716-4466554f0002';

  private const string OTHER_INTERVENTION_ID = 'cc0e8400-e29b-41d4-a716-4466554f0003';

  private const string FACILITY_ID = 'cc0e8400-e29b-41d4-a716-4466554f0004';

  private const string OTHER_FACILITY_ID = 'cc0e8400-e29b-41d4-a716-4466554f0005';

  private const string MATCHING_EQUIPMENT_ID = 'cc0e8400-e29b-41d4-a716-4466554f0010';

  private const string OTHER_FACILITY_EQUIPMENT_ID = 'cc0e8400-e29b-41d4-a716-4466554f0011';

  private const string OTHER_INTERVENTION_EQUIPMENT_ID = 'cc0e8400-e29b-41d4-a716-4466554f0012';

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
    $organization->name = 'Canonical Equipment Provider Test';
    $organization->slug = 'canonical-equipment-provider-test';
    $organization->ownerUserId = 'cc0e8400-e29b-41d4-a716-4466554f9000';
    $organization->createdByUserId = 'cc0e8400-e29b-41d4-a716-4466554f9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $this->persistEquipment(
      self::MATCHING_EQUIPMENT_ID,
      $organization,
      self::INTERVENTION_ID,
      self::FACILITY_ID,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
    $this->persistEquipment(
      self::OTHER_FACILITY_EQUIPMENT_ID,
      $organization,
      self::INTERVENTION_ID,
      self::OTHER_FACILITY_ID,
      new DateTimeImmutable('2026-01-01T00:00:01+00:00'),
    );
    $this->persistEquipment(
      self::OTHER_INTERVENTION_EQUIPMENT_ID,
      $organization,
      self::OTHER_INTERVENTION_ID,
      self::FACILITY_ID,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

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
  public function testProvideNarrowsTheCollectionByBothInterventionAndFacility(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      '/api/equipment?intervention=/api/interventions/' . self::INTERVENTION_ID
      . '&facility=/api/facilities/' . self::FACILITY_ID,
    ));

    $result = $this->provider($requestStack)->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    $first = $items[0];
    self::assertInstanceOf(EquipmentOutput::class, $first);
    self::assertSame(self::MATCHING_EQUIPMENT_ID, $first->id);
    self::assertSame(self::FACILITY_ID, $first->facilityId);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $first->intervention);
    self::assertSame('draft', $first->recordStatus);
  }

  #[Test]
  public function testProvidePaginatesTheInterventionFilteredCollection(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/equipment?intervention=/api/interventions/' . self::INTERVENTION_ID));
    $provider = $this->provider($requestStack);

    $firstPage = $provider->provide(new GetCollection(), [], ['filters' => ['page' => '1', 'itemsPerPage' => '1']]);
    self::assertInstanceOf(TraversablePaginator::class, $firstPage);
    self::assertSame(2.0, $firstPage->getTotalItems());
    self::assertSame([self::MATCHING_EQUIPMENT_ID], $this->identifiers($firstPage));

    $secondPage = $provider->provide(new GetCollection(), [], ['filters' => ['page' => '2', 'itemsPerPage' => '1']]);
    self::assertInstanceOf(TraversablePaginator::class, $secondPage);
    self::assertSame(2.0, $secondPage->getTotalItems());
    self::assertSame([self::OTHER_FACILITY_EQUIPMENT_ID], $this->identifiers($secondPage));
  }

  /**
   * @param TraversablePaginator<EquipmentOutput> $page
   *
   * @return list<string>
   */
  private function identifiers(TraversablePaginator $page): array
  {
    return array_map(
      static fn (EquipmentOutput $output): string => (string) $output->id,
      array_values(iterator_to_array($page)),
    );
  }

  private function provider(RequestStack $requestStack): CanonicalEquipmentProvider
  {
    $authorization = self::createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $security = self::createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('cc0e8400-e29b-41d4-a716-4466554f9000', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $resources = self::createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft'),
    );

    return new CanonicalEquipmentProvider(
      $this->entityManager,
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($resources),
    );
  }

  private function persistEquipment(
    string $id,
    OrganizationRecord $organization,
    string $interventionId,
    string $facilityId,
    ?DateTimeImmutable $createdAt = null,
  ): void {
    $record = new EquipmentRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->interventionId = $interventionId;
    $record->facilityId = $facilityId;
    $record->recordStatus = 'draft';
    $record->type = 'fire_extinguisher';
    $record->status = 'in_stock';
    $record->createdAt = $createdAt ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM equipment WHERE organization_id IN (:organizationIds)',
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
