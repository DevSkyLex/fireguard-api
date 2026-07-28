<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\{Get, GetCollection};
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_map;
use function array_values;
use function iterator_to_array;

/**
 * Test CanonicalInspectionProviderTest.
 *
 * The collection branch of this provider builds its DQL by hand (organization
 * scoping, recordStatus defaulting, intervention and equipment filters,
 * pagination). Only a real database parses that DQL, so the whole provider is
 * exercised here against PostgreSQL rather than against a mocked QueryBuilder.
 *
 * @category Presentation Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalInspectionProvider::class)]
final class CanonicalInspectionProviderTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '991e8400-e29b-41d4-a716-446655491001';

  private const string OTHER_ORGANIZATION_ID = '991e8400-e29b-41d4-a716-446655491002';

  private const string PUBLISHED_A_ID = '991e8400-e29b-41d4-a716-446655491101';

  private const string PUBLISHED_B_ID = '991e8400-e29b-41d4-a716-446655491102';

  private const string DRAFT_ID = '991e8400-e29b-41d4-a716-446655491103';

  private const string FOREIGN_ID = '991e8400-e29b-41d4-a716-446655491104';

  private const string INTERVENTION_ID = '991e8400-e29b-41d4-a716-446655492001';

  private const string EQUIPMENT_A_ID = '991e8400-e29b-41d4-a716-446655493001';

  private const string EQUIPMENT_B_ID = '991e8400-e29b-41d4-a716-446655493002';

  private const string USER_ID = '991e8400-e29b-41d4-a716-446655499000';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->entityManager->persist($this->createOrganization(self::ORGANIZATION_ID, 'canonical-inspection-org'));
    $this->entityManager->persist($this->createOrganization(self::OTHER_ORGANIZATION_ID, 'canonical-inspection-org-b'));
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testProvideMapsASingleInspectionIntoTheCanonicalOutput(): void
  {
    $record = $this->createInspection(self::PUBLISHED_A_ID);
    $record->interventionId = self::INTERVENTION_ID;
    $record->notes = 'Checked everything';
    $record->signature = 'signed';
    $record->inspectorUserId = self::USER_ID;
    $record->inspectorOrganizationName = 'External Auditors';
    $this->entityManager->flush();
    $this->entityManager->clear();

    $output = $this->provider()->provide(new Get(), ['id' => self::PUBLISHED_A_ID]);

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame(self::PUBLISHED_A_ID, $output->id);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $output->intervention);
    self::assertSame('published', $output->recordStatus);
    self::assertSame(self::EQUIPMENT_A_ID, $output->equipmentId);
    self::assertSame('Checked everything', $output->notes);
    self::assertSame('signed', $output->signature);
    self::assertSame(0, $output->nonConformitiesCount);
    self::assertSame(self::USER_ID, $output->inspector?->id);
    self::assertSame('External Auditors', $output->inspector->organizationName);
  }

  #[Test]
  public function testProvideReportsAnUnknownInspectionAsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->provider()->provide(new Get(), ['id' => self::PUBLISHED_B_ID]);
  }

  #[Test]
  public function testProvideRejectsAnItemReadWithoutThePermission(): void
  {
    $this->createInspection(self::PUBLISHED_A_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(AccessDeniedHttpException::class);

    $this->provider(permitted: false)->provide(new Get(), ['id' => self::PUBLISHED_A_ID]);
  }

  #[Test]
  public function testProvideRejectsAnItemReadWithoutAnAuthenticatedUser(): void
  {
    $this->createInspection(self::PUBLISHED_A_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(AccessDeniedHttpException::class);

    $this->provider(authenticated: false)->provide(new Get(), ['id' => self::PUBLISHED_A_ID]);
  }

  #[Test]
  public function testProvideListsPublishedInspectionsScopedToTheOrganization(): void
  {
    $this->createInspection(self::PUBLISHED_A_ID);
    $this->createInspection(self::PUBLISHED_B_ID);
    $draft = $this->createInspection(self::DRAFT_ID);
    $draft->recordStatus = 'draft';
    $draft->interventionId = self::INTERVENTION_ID;
    $foreign = $this->createInspection(self::FOREIGN_ID);
    $foreign->organization = $this->entityManager->getReference(OrganizationRecord::class, self::OTHER_ORGANIZATION_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->provider(query: ['organization' => '/api/organizations/' . self::ORGANIZATION_ID])
      ->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $page);
    self::assertSame(2.0, $page->getTotalItems());
    self::assertSame(
      [self::PUBLISHED_A_ID, self::PUBLISHED_B_ID],
      $this->identifiers($page),
    );
  }

  #[Test]
  public function testProvideListsInterventionDraftsResolvedThroughTheInterventionFilter(): void
  {
    $this->createInspection(self::PUBLISHED_A_ID);
    $draft = $this->createInspection(self::DRAFT_ID);
    $draft->recordStatus = 'draft';
    $draft->interventionId = self::INTERVENTION_ID;
    $this->entityManager->flush();
    $this->entityManager->clear();

    // No `organization` query parameter: the organization is resolved from the
    // intervention, and the record status defaults to `draft`.
    $page = $this->provider(query: ['intervention' => '/api/interventions/' . self::INTERVENTION_ID])
      ->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $page);
    self::assertSame([self::DRAFT_ID], $this->identifiers($page));
  }

  #[Test]
  public function testProvideFiltersByEquipmentAndExplicitRecordStatus(): void
  {
    $this->createInspection(self::PUBLISHED_A_ID);
    $other = $this->createInspection(self::PUBLISHED_B_ID);
    $other->equipmentId = self::EQUIPMENT_B_ID;
    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->provider(query: [
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'equipment' => '/api/equipment/' . self::EQUIPMENT_B_ID,
      'recordStatus' => 'published',
    ])->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $page);
    self::assertSame([self::PUBLISHED_B_ID], $this->identifiers($page));
  }

  #[Test]
  public function testProvidePaginatesTheCollectionFromTheRequestFilters(): void
  {
    $this->createInspection(self::PUBLISHED_A_ID);
    $this->createInspection(self::PUBLISHED_B_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $provider = $this->provider(query: ['organization' => '/api/organizations/' . self::ORGANIZATION_ID]);

    $secondPage = $provider->provide(new GetCollection(), [], ['filters' => ['page' => '2', 'itemsPerPage' => '1']]);
    self::assertInstanceOf(TraversablePaginator::class, $secondPage);
    self::assertSame([self::PUBLISHED_B_ID], $this->identifiers($secondPage));

    // Out-of-range and non-numeric filters fall back to the defaults.
    $defaults = $provider->provide(new GetCollection(), [], ['filters' => ['page' => '-3', 'itemsPerPage' => 'many']]);
    self::assertInstanceOf(TraversablePaginator::class, $defaults);
    self::assertSame([self::PUBLISHED_A_ID, self::PUBLISHED_B_ID], $this->identifiers($defaults));
  }

  #[Test]
  public function testProvideRequiresAnOrganizationOrInterventionFilter(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->provider()->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideReportsAnUnknownOrganizationAsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->provider(query: ['organization' => '/api/organizations/991e8400-e29b-41d4-a716-4466554910ff'])
      ->provide(new GetCollection(), []);
  }

  /**
   * @param TraversablePaginator<InspectionOutput> $page
   *
   * @return list<string>
   */
  private function identifiers(TraversablePaginator $page): array
  {
    return array_map(
      static fn (InspectionOutput $output): string => (string) $output->id,
      array_values(iterator_to_array($page)),
    );
  }

  /**
   * @param array<string, string> $query
   */
  private function provider(
    array $query = [],
    bool $permitted = true,
    bool $authenticated = true,
  ): CanonicalInspectionProvider {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/inspections', 'GET', $query));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($permitted);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      $authenticated ? new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true) : null,
    );

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft'),
    );

    return new CanonicalInspectionProvider(
      $this->entityManager,
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($gateway),
    );
  }

  private function createInspection(string $id): InspectionRecord
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InspectionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->equipmentId = self::EQUIPMENT_A_ID;
    $record->inspectorType = 'external';
    $record->inspectorName = 'Inspector';
    $record->result = 'pass';
    $record->status = 'submitted';
    $record->performedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');
    $record->createdAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);

    return $record;
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Canonical Inspection ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = self::USER_ID;
    $organization->createdByUserId = self::USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id IN (:inspectionIds)',
      ['inspectionIds' => [self::PUBLISHED_A_ID, self::PUBLISHED_B_ID, self::DRAFT_ID, self::FOREIGN_ID]],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
