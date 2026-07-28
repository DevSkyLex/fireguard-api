<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Presentation\Api\Provider\InspectionResponse;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Inspection\Presentation\Api\Provider\InspectionResponse\InspectionResponseProvider;
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
 * Test InspectionResponseProviderTest.
 *
 * The collection branch assembles its DQL by hand — organization scoping,
 * intervention / inspection filters, the record-status default and
 * pagination — and resolves the owning organization from whichever filter the
 * caller supplied. Only a real database parses that query, so the collection
 * branch is exercised here against PostgreSQL; the item branch is unit-tested.
 *
 * @category Presentation Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionResponseProvider::class)]
final class InspectionResponseProviderTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '992e8400-e29b-41d4-a716-446655492001';

  private const string OTHER_ORGANIZATION_ID = '992e8400-e29b-41d4-a716-446655492002';

  private const string INSPECTION_ID = '992e8400-e29b-41d4-a716-446655492101';

  private const string PUBLISHED_A_ID = '992e8400-e29b-41d4-a716-446655492201';

  private const string PUBLISHED_B_ID = '992e8400-e29b-41d4-a716-446655492202';

  private const string DRAFT_ID = '992e8400-e29b-41d4-a716-446655492203';

  private const string FOREIGN_ID = '992e8400-e29b-41d4-a716-446655492204';

  private const string INTERVENTION_ID = '992e8400-e29b-41d4-a716-446655492301';

  private const string USER_ID = '992e8400-e29b-41d4-a716-446655499000';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->entityManager->persist($this->createOrganization(self::ORGANIZATION_ID, 'inspection-response-org'));
    $this->entityManager->persist($this->createOrganization(self::OTHER_ORGANIZATION_ID, 'inspection-response-org-b'));
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testProvideListsPublishedResponsesScopedToTheOrganization(): void
  {
    $this->createResponse(self::PUBLISHED_A_ID, 'item-a');
    $this->createResponse(self::PUBLISHED_B_ID, 'item-b');
    $draft = $this->createResponse(self::DRAFT_ID, 'item-draft');
    $draft->recordStatus = 'draft';
    $draft->interventionId = self::INTERVENTION_ID;
    $foreign = $this->createResponse(self::FOREIGN_ID, 'item-foreign');
    $foreign->organization = $this->entityManager->getReference(OrganizationRecord::class, self::OTHER_ORGANIZATION_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->provider(['organization' => '/api/organizations/' . self::ORGANIZATION_ID])
      ->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $page);
    self::assertSame(2.0, $page->getTotalItems());
    self::assertSame([self::PUBLISHED_A_ID, self::PUBLISHED_B_ID], $this->identifiers($page));
  }

  #[Test]
  public function testProvideResolvesTheOrganizationFromTheInterventionFilterAndDefaultsToDrafts(): void
  {
    $this->createResponse(self::PUBLISHED_A_ID, 'item-a');
    $draft = $this->createResponse(self::DRAFT_ID, 'item-draft');
    $draft->recordStatus = 'draft';
    $draft->interventionId = self::INTERVENTION_ID;
    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->provider(['intervention' => '/api/interventions/' . self::INTERVENTION_ID])
      ->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $page);
    self::assertSame([self::DRAFT_ID], $this->identifiers($page));
  }

  #[Test]
  public function testProvideResolvesTheOrganizationFromTheInspectionFilter(): void
  {
    $this->createInspection();
    $this->createResponse(self::PUBLISHED_A_ID, 'item-a');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->provider(['inspection' => '/api/inspections/' . self::INSPECTION_ID])
      ->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $page);
    self::assertSame([self::PUBLISHED_A_ID], $this->identifiers($page));
  }

  #[Test]
  public function testProvidePaginatesAndHonoursAnExplicitRecordStatus(): void
  {
    $this->createResponse(self::PUBLISHED_A_ID, 'item-a');
    $this->createResponse(self::PUBLISHED_B_ID, 'item-b');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $provider = $this->provider([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'recordStatus' => 'published',
    ]);

    $secondPage = $provider->provide(new GetCollection(), [], ['filters' => ['page' => '2', 'itemsPerPage' => '1']]);
    self::assertInstanceOf(TraversablePaginator::class, $secondPage);
    self::assertSame([self::PUBLISHED_B_ID], $this->identifiers($secondPage));

    // Non-numeric filters fall back to the defaults.
    $defaults = $provider->provide(new GetCollection(), [], ['filters' => ['page' => 'first', 'itemsPerPage' => 'many']]);
    self::assertInstanceOf(TraversablePaginator::class, $defaults);
    self::assertSame([self::PUBLISHED_A_ID, self::PUBLISHED_B_ID], $this->identifiers($defaults));
  }

  #[Test]
  public function testProvideRequiresAtLeastOneScopingFilter(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->provider([])->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideReportsAnUnknownInspectionScopeAsBadRequest(): void
  {
    // The inspection filter resolves to nothing, so no organization can be
    // derived and the request is rejected before any query runs.
    $this->expectException(BadRequestHttpException::class);

    $this->provider(['inspection' => '/api/inspections/992e8400-e29b-41d4-a716-4466554921ff'])
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideReportsAnUnknownOrganizationAsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->provider(['organization' => '/api/organizations/992e8400-e29b-41d4-a716-4466554920ff'])
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideRejectsACollectionReadWithoutThePermission(): void
  {
    $this->expectException(AccessDeniedHttpException::class);

    $this->provider(['organization' => '/api/organizations/' . self::ORGANIZATION_ID], permitted: false)
      ->provide(new GetCollection(), []);
  }

  /**
   * @param TraversablePaginator<InspectionResponseOutput> $page
   *
   * @return list<string>
   */
  private function identifiers(TraversablePaginator $page): array
  {
    return array_map(
      static fn (InspectionResponseOutput $output): string => (string) $output->id,
      array_values(iterator_to_array($page)),
    );
  }

  /**
   * @param array<string, string> $query
   */
  private function provider(array $query, bool $permitted = true): InspectionResponseProvider
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/inspection_responses', 'GET', $query));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($permitted);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft'),
    );

    return new InspectionResponseProvider(
      entityManager: $this->entityManager,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
      interventionResourceManager: new InterventionResourceManager($gateway),
    );
  }

  private function createResponse(string $id, string $itemKey): InspectionResponseRecord
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InspectionResponseRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->inspectionId = self::INSPECTION_ID;
    $record->itemKey = $itemKey;
    $record->value = 'ok';
    $record->createdAt = new DateTimeImmutable('2026-01-01T08:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);

    return $record;
  }

  private function createInspection(): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InspectionRecord();
    $record->id = self::INSPECTION_ID;
    $record->organization = $organization;
    $record->equipmentId = '992e8400-e29b-41d4-a716-446655493001';
    $record->inspectorType = 'external';
    $record->inspectorName = 'Inspector';
    $record->result = 'pass';
    $record->status = 'submitted';
    $record->performedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');
    $record->createdAt = $record->performedAt;
    $record->updatedAt = $record->performedAt;
    $this->entityManager->persist($record);
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Inspection Response ' . $slug;
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
      'DELETE FROM inspection_responses WHERE id IN (:responseIds)',
      ['responseIds' => [self::PUBLISHED_A_ID, self::PUBLISHED_B_ID, self::DRAFT_ID, self::FOREIGN_ID]],
      ['responseIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id IN (:inspectionIds)',
      ['inspectionIds' => [self::INSPECTION_ID]],
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
