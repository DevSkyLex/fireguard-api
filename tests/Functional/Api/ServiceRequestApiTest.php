<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\DataProvider;
use ServiceRequest\Application\Port\Outbound\ServiceRequestRepositoryPort;
use ServiceRequest\Application\UseCase\Command\ConvertServiceRequest\{ConvertServiceRequestCommand, ConvertServiceRequestResult};
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\{ServiceRequestContent, ServiceRequestTarget};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class ServiceRequestApiTest
 *
 * Exercises request permissions, target ownership, revision-bound decisions and atomic repair conversion on PostgreSQL.
 * Each HTTP scenario performs one request to retain the suite's stateless test authentication.
 *
 * @category FunctionalTest
 */
final class ServiceRequestApiTest extends WebTestCase
{
  private const string ORG = 'ad69061e-b12f-41de-8a66-000000000001';

  private const string OWNER = 'ad69061e-b12f-41de-8a66-000000000002';

  private const string MEMBER = 'ad69061e-b12f-41de-8a66-000000000003';

  private const string CUSTOMER = 'ad69061e-b12f-41de-8a66-000000000010';

  private const string SITE = 'ad69061e-b12f-41de-8a66-000000000020';

  private const string BUILDING = 'ad69061e-b12f-41de-8a66-000000000021';

  private const string EQUIPMENT = 'ad69061e-b12f-41de-8a66-000000000030';

  private const string REQUEST = 'ad69061e-b12f-41de-8a66-000000000040';

  private const string OPERATION = 'ad69061e-b12f-41de-8a66-000000000050';

  private const string OTHER_ORG = 'ad69061e-b12f-41de-8a66-000000000090';

  public function testCreateDerivesRootAndRetainsMinimalCustomerWithoutCustomerRead(): void
  {
    $client = $this->client(permissions: ['organization.service_requests.create']);
    $this->seedTarget();
    $body = $this->request($client, 'POST', '', $this->createBody());
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('Seal damaged', $body['title']);
    self::assertSame('requested', $body['status']);
    self::assertSame(self::SITE, $body['siteId']);
    self::assertSame(1, $body['revision']);
    /** @var array{customer:array<string,mixed>,site:array{id:string,name:string},equipment:array{id:string,name:string}} $snapshot */
    $snapshot = $body['targetSnapshot'];
    self::assertSame(['id' => self::CUSTOMER, 'name' => 'Internal client'], $snapshot['customer']);
    self::assertSame(['id' => self::SITE, 'name' => 'Fire site'], $snapshot['site']);
    self::assertSame(self::EQUIPMENT, $snapshot['equipment']['id']);
    self::assertArrayNotHasKey('contacts', $snapshot['customer']);
    self::assertNull($body['interventionId']);
  }

  public function testCreateSiteOnlyRequestWithoutMandatoryCustomer(): void
  {
    $client = $this->client();
    $this->seedTarget(customer: false);
    $body = $this->request($client, 'POST', '', ['siteId' => self::SITE, 'title' => 'Locate repair target', 'description' => 'Damage reported in the storage room.']);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertNull($body['equipmentId']);
    self::assertSame('requested', $body['status']);
    /** @var array{customer:mixed} $snapshot */ $snapshot = $body['targetSnapshot'];
    self::assertNull($snapshot['customer']);
  }

  public function testStockEquipmentCanBeRequestedWithoutClaimingASite(): void
  {
    $client = $this->client();
    $equipment = $this->seedTarget();
    $equipment->facilityId = null;
    $equipment->status = 'in_stock';
    $this->main()->flush();
    $body = $this->request($client, 'POST', '', $this->createBody());
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertNull($body['siteId']);
    /** @var array{site:mixed,customer:mixed} $snapshot */ $snapshot = $body['targetSnapshot'];
    self::assertNull($snapshot['site']);
    self::assertNull($snapshot['customer']);
  }

  #[DataProvider('unavailableTargets')]
  public function testCreationRefusesUnavailableTargetsWithoutSaving(string $scenario, int $status): void
  {
    $client = $this->client();
    $equipment = $this->seedTarget();
    $body = $this->createBody();
    if ('retired' === $scenario) {
      $equipment->status = 'decommissioned';
    } elseif ('draft' === $scenario) {
      $equipment->recordStatus = 'draft';
    } elseif ('foreign' === $scenario) {
      $equipment->organization = $this->organization(self::OTHER_ORG);
    } elseif ('archived' === $scenario) {
      $this->main()->getConnection()->executeStatement("UPDATE facilities SET status = 'archived' WHERE id = ?", [self::SITE]);
    } elseif ('missing' === $scenario) {
      $body['equipmentId'] = 'ad69061e-b12f-41de-8a66-000000000099';
    } else {
      unset($body['equipmentId']);
    }
    $this->main()->flush();
    $this->request($client, 'POST', '', $body);
    self::assertSame($status, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(0, $this->repository()->count(self::ORG, null, null, null, ''));
  }

  /**
   * @return iterable<string,array{string,int}>
   */
  public static function unavailableTargets(): iterable
  {
    yield 'retired' => ['retired', 409];
    yield 'archived ancestor' => ['archived', 409];
    yield 'draft' => ['draft', 422];
    yield 'foreign' => ['foreign', 422];
    yield 'missing' => ['missing', 422];
    yield 'no target' => ['none', 422];
  }

  public function testCreationRefusesAnEquipmentOutsideExplicitSite(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $site = new FacilityRecord();
    $site->id = 'ad69061e-b12f-41de-8a66-000000000022';
    $site->organization = $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $site->name = 'Other site';
    $site->type = 'site';
    $site->createdAt = $site->updatedAt = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    $this->main()->persist($site);
    $this->main()->flush();
    $this->request($client, 'POST', '', $this->createBody() + ['siteId' => $site->id]);
    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  public function testCreationRefusesAnUnprovenInspectionOrigin(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->request($client, 'POST', '', $this->createBody() + ['originInspectionId' => 'ad69061e-b12f-41de-8a66-000000000060']);
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(0, $this->repository()->count(self::ORG, null, null, null, ''));
  }

  #[DataProvider('originScopes')]
  public function testOriginRequiresPublishedMatchingInspectionAndFinding(string $scenario, int $expected): void
  {
    $client = $this->client(permissions: ['organization.service_requests.create']);
    $this->seedTarget();
    $inspection = new InspectionRecord();
    $inspection->id = 'ad69061e-b12f-41de-8a66-000000000060';
    $inspection->organization = 'foreign' === $scenario ? $this->organization(self::OTHER_ORG) : $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $inspection->equipmentId = 'equipment' === $scenario ? 'ad69061e-b12f-41de-8a66-000000000039' : self::EQUIPMENT;
    $inspection->facilityId = 'site' === $scenario ? null : self::BUILDING;
    $inspection->recordStatus = 'draft' === $scenario ? 'draft' : 'published';
    $inspection->status = 'closed';
    $inspection->result = 'fail';
    $inspection->inspectorType = 'internal';
    $inspection->inspectorName = 'Technician';
    $inspection->performedAt = $inspection->createdAt = $inspection->updatedAt = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    $this->main()->persist($inspection);
    $finding = new NonConformityRecord();
    $finding->id = 'ad69061e-b12f-41de-8a66-000000000061';
    $finding->inspection = $inspection;
    $finding->description = 'Broken seal';
    $finding->severity = 'major';
    $finding->status = 'open';
    $finding->createdAt = $finding->updatedAt = $inspection->createdAt;
    $this->main()->persist($finding);
    $this->main()->flush();
    $origin = ['originInspectionId' => 'inspection selection' === $scenario ? 'ad69061e-b12f-41de-8a66-000000000063' : $inspection->id, 'originNonConformityId' => 'missing finding' === $scenario ? 'ad69061e-b12f-41de-8a66-000000000062' : $finding->id];
    $body = $this->request($client, 'POST', '', $this->createBody() + $origin);
    self::assertSame($expected, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    if (201 === $expected) {
      self::assertSame($inspection->id, $body['originInspectionId']);
      self::assertSame($finding->id, $body['originNonConformityId']);
    } else {
      self::assertSame(0, $this->repository()->count(self::ORG, null, null, null, ''));
    }
  }

  /**
   * @return iterable<string,array{string,int}>
   */
  public static function originScopes(): iterable
  {
    yield 'matching published inspection and finding' => ['valid', 201];
    yield 'foreign inspection and finding' => ['foreign', 422];
    yield 'unpublished inspection' => ['draft', 422];
    yield 'different equipment' => ['equipment', 422];
    yield 'different site' => ['site', 422];
    yield 'finding belongs to another selected inspection' => ['inspection selection', 422];
    yield 'unknown finding' => ['missing finding', 422];
  }

  public function testHistoricalReadRetainsTargetAndDropsUnexpectedPrivateFields(): void
  {
    $client = $this->client(permissions: ['organization.service_requests.read']);
    $equipment = $this->seedTarget();
    $this->seedRequest(extraSnapshot: ['customer' => ['id' => self::CUSTOMER, 'name' => 'Original client', 'contacts' => [['name' => 'Private', 'email' => 'private@example.com']]], 'privateSource' => 'hidden']);
    $equipment->status = 'decommissioned';
    $equipment->name = 'Changed now';
    $this->main()->flush();
    $body = $this->request($client, 'GET', '/' . self::REQUEST);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    /** @var array{equipment:array{name:string},customer:array<string,mixed>} $snapshot */ $snapshot = $body['targetSnapshot'];
    self::assertSame('Extinguisher A', $snapshot['equipment']['name']);
    self::assertSame(['id' => self::CUSTOMER, 'name' => 'Original client'], $snapshot['customer']);
    self::assertArrayNotHasKey('privateSource', $snapshot);
  }

  public function testListFiltersCountAndPageWithinOrganization(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $this->seedRequest(id: 'ad69061e-b12f-41de-8a66-000000000041');
    $this->organization(self::OTHER_ORG);
    $this->seedRequest(id: 'ad69061e-b12f-41de-8a66-000000000042', organizationId: self::OTHER_ORG, qualified: true);
    $body = $this->request($client, 'GET', '?status=qualified&equipmentId=' . self::EQUIPMENT . '&siteId=' . self::SITE . '&search=Seal&itemsPerPage=1');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $body['totalItems']);
    /** @var list<array{id:string}> $members */ $members = $body['member'];
    self::assertCount(1, $members);
    self::assertSame(self::REQUEST, $members[0]['id']);
  }

  #[DataProvider('permissionRefusals')]
  public function testPermissionsAndOrganizationScopesAreChecked(string $method, string $path, string $actor, int $expected): void
  {
    $client = $this->client($actor);
    $this->seedTarget();
    $this->seedRequest();
    $this->request($client, $method, $path, 'PATCH' === $method ? ['title' => 'Updated'] : $this->createBody(), 1);
    self::assertSame($expected, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * @return iterable<string,array{string,string,string,int}>
   */
  public static function permissionRefusals(): iterable
  {
    yield 'unentitled read' => ['GET', '', self::MEMBER, 403];
    yield 'unentitled create' => ['POST', '', self::MEMBER, 403];
    yield 'unentitled manage' => ['PATCH', '/' . self::REQUEST, self::MEMBER, 403];
    yield 'outside read' => ['GET', '', 'ad69061e-b12f-41de-8a66-000000000099', 404];
    yield 'outside create' => ['POST', '', 'ad69061e-b12f-41de-8a66-000000000099', 404];
  }

  public function testForeignRequestIdentityIsHidden(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->organization(self::OTHER_ORG);
    $this->seedRequest(organizationId: self::OTHER_ORG);
    $this->request($client, 'GET', '/' . self::REQUEST);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  public function testPatchChangesDescriptionAndKeepsTargetAtExpectedRevision(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest();
    $body = $this->request($client, 'PATCH', '/' . self::REQUEST, ['description' => ' Replace the seal. ', 'priority' => 'high'], 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('Replace the seal.', $body['description']);
    self::assertSame('high', $body['priority']);
    self::assertSame(self::EQUIPMENT, $body['equipmentId']);
    self::assertSame(2, $body['revision']);
  }

  #[DataProvider('revisionRefusals')]
  public function testRevisionPreconditionsPreventMutation(?string $header, int $expected): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest();
    $this->request($client, 'PATCH', '/' . self::REQUEST, ['title' => 'New'], rawRevision: $header);
    self::assertSame($expected, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $this->repository()->find(self::REQUEST, self::ORG)?->revision);
  }

  /**
   * @return iterable<string,array{?string,int}>
   */
  public static function revisionRefusals(): iterable
  {
    yield 'missing' => [null, 428];
    yield 'stale' => ['"revision-2"', 412];
    yield 'malformed' => ['1', 412];
  }

  public function testSiteOnlyQualificationSelectsEquipmentWithinOriginalSite(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(equipmentId: null);
    $body = $this->request($client, 'POST', '/' . self::REQUEST . '/qualify', ['equipmentId' => self::EQUIPMENT, 'note' => 'Verified target'], 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('qualified', $body['status']);
    self::assertSame(self::EQUIPMENT, $body['equipmentId']);
    self::assertSame(self::SITE, $body['siteId']);
    self::assertSame('Verified target', $body['qualificationNote']);
    self::assertSame(3, $body['revision']);
    self::assertNotNull($body['qualifiedAt']);
  }

  public function testSiteOnlyQualificationRequiresEquipmentBeforeWork(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(equipmentId: null);
    $this->request($client, 'POST', '/' . self::REQUEST . '/qualify', ['note' => 'No equipment chosen'], 1);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    self::assertSame('requested', $this->repository()->find(self::REQUEST, self::ORG)?->status);
  }

  #[DataProvider('decisions')]
  public function testExplicitDecisionKeepsTargetAndReason(string $action, string $expected): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $body = $this->request($client, 'POST', '/' . self::REQUEST . '/' . $action, ['reason' => 'Duplicate field report'], 2);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame($expected, $body['status']);
    self::assertSame('Duplicate field report', $body['decisionReason']);
    self::assertSame(self::EQUIPMENT, $body['equipmentId']);
    self::assertNotNull($body['qualifiedAt']);
  }

  /**
   * @return iterable<string,array{string,string}>
   */
  public static function decisions(): iterable
  {
    yield 'reject' => ['reject', 'rejected'];
    yield 'cancel' => ['cancel', 'cancelled'];
  }

  public function testConversionCreatesAndLinksExactlyOneCorrectiveRepair(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $body = $this->request($client, 'POST', '/' . self::REQUEST . '/convert', ['clientOperationId' => self::OPERATION], 2);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('converted', $body['status']);
    self::assertSame(3, $body['revision']);
    self::assertIsString($body['interventionId']);
    self::assertIsString($body['taskId']);
    self::assertNotNull($body['convertedAt']);
    self::assertSame(1, $this->main()->getRepository(InterventionRecord::class)->count(['organization' => self::ORG]));
    $task = $this->main()->find(InterventionWorkItemRecord::class, $body['taskId']);
    self::assertInstanceOf(InterventionWorkItemRecord::class, $task);
    self::assertSame('repair', $task->action);
    self::assertSame('/api/equipment/' . self::EQUIPMENT, $task->target);
    self::assertSame($body['interventionId'], $this->repository()->conversionReceiptByOperation(self::ORG, self::OPERATION)?->interventionId);
  }

  public function testConversionReplayAcceptsOriginalRevisionAfterRetirementAndPublishedWork(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $first = $this->convert();
    $this->main()->getConnection()->executeStatement("UPDATE equipment SET status = 'decommissioned' WHERE id = ?", [self::EQUIPMENT]);
    $this->main()->getConnection()->executeStatement("UPDATE facilities SET status = 'archived' WHERE id = ?", [self::SITE]);
    $this->main()->getConnection()->executeStatement("UPDATE interventions SET status = 'published' WHERE id = ?", [$first->request->interventionId]);
    $this->main()->clear();
    $body = $this->request($client, 'POST', '/' . self::REQUEST . '/convert', ['clientOperationId' => self::OPERATION], 2);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame($first->request->interventionId, $body['interventionId']);
    self::assertSame($first->request->taskId, $body['taskId']);
    self::assertSame(3, $body['revision']);
    self::assertSame(1, $this->main()->getRepository(InterventionRecord::class)->count(['organization' => self::ORG]));
  }

  public function testConversionLinksAnOpenFailedRepairAndPreservesItsExecutionHistory(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $order = new InterventionRecord();
    $order->id = 'ad69061e-b12f-41de-8a66-000000000070';
    $order->organization = $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $order->name = 'Repair already open';
    $order->type = 'corrective_maintenance';
    $order->siteId = self::SITE;
    $order->status = 'in_progress';
    $order->number = 1;
    $order->createdAt = $order->updatedAt = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    $this->main()->persist($order);
    $task = new InterventionWorkItemRecord();
    $task->id = 'ad69061e-b12f-41de-8a66-000000000071';
    $task->intervention = $order;
    $task->action = 'repair';
    $task->target = '/api/equipment/' . self::EQUIPMENT;
    $task->status = 'in_progress';
    $task->revision = 9;
    $task->executionResult = ['equipmentId' => self::EQUIPMENT, 'performedAt' => '2026-10-05T10:00:00+00:00', 'outcome' => 'failed', 'workPerformed' => 'Seal unavailable', 'state' => 'staged', 'validatedAt' => null, 'history' => [['equipmentId' => self::EQUIPMENT, 'performedAt' => '2026-10-04T10:00:00+00:00', 'outcome' => 'failed', 'workPerformed' => 'Initial assessment']]];
    $task->resultResource = $task->target;
    $task->createdAt = $task->updatedAt = $order->createdAt;
    $this->main()->persist($task);
    $this->main()->flush();
    $proof = $task->executionResult;
    $body = $this->request($client, 'POST', '/' . self::REQUEST . '/convert', ['clientOperationId' => self::OPERATION, 'existingInterventionId' => $order->id, 'existingTaskId' => $task->id], 2);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('converted', $body['status']);
    self::assertSame($order->id, $body['interventionId']);
    self::assertSame($task->id, $body['taskId']);
    $retained = $this->main()->find(InterventionWorkItemRecord::class, $task->id);
    self::assertInstanceOf(InterventionWorkItemRecord::class, $retained);
    self::assertSame($proof, $retained->executionResult);
    self::assertSame(9, $retained->revision);
    self::assertSame('/api/equipment/' . self::EQUIPMENT, $retained->resultResource);
    self::assertSame(1, $this->main()->getRepository(InterventionWorkItemRecord::class)->count(['intervention' => $order->id]));
  }

  public function testChangedConversionSelectionConflictsWithoutDuplicatingWork(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $first = $this->convert();
    $this->request($client, 'POST', '/' . self::REQUEST . '/convert', ['clientOperationId' => self::OPERATION, 'existingInterventionId' => $first->request->interventionId], 2);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $this->main()->getRepository(InterventionRecord::class)->count(['organization' => self::ORG]));
    self::assertSame($first->request->taskId, $this->repository()->find(self::REQUEST, self::ORG)?->taskId);
  }

  public function testOperationCollisionBetweenRequestsPreservesSecondQualifiedRequest(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $second = 'ad69061e-b12f-41de-8a66-000000000041';
    $this->seedRequest(id: $second, qualified: true);
    $this->convert();
    $this->request($client, 'POST', '/' . $second . '/convert', ['clientOperationId' => self::OPERATION], 2);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('qualified', $this->repository()->find($second, self::ORG)?->status);
    self::assertNull($this->repository()->conversionReceiptForRequest($second, self::ORG));
    self::assertSame(1, $this->main()->getRepository(InterventionRecord::class)->count(['organization' => self::ORG]));
  }

  public function testConversionRequiresInterventionPlanningBeyondRequestManagement(): void
  {
    $client = $this->client(permissions: ['organization.service_requests.manage']);
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $this->request($client, 'POST', '/' . self::REQUEST . '/convert', ['clientOperationId' => self::OPERATION], 2);
    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('qualified', $this->repository()->find(self::REQUEST, self::ORG)?->status);
    self::assertSame(0, $this->main()->getRepository(InterventionRecord::class)->count(['organization' => self::ORG]));
  }

  public function testExplicitForeignInterventionIsHiddenDuringConversion(): void
  {
    $client = $this->client();
    $this->seedTarget();
    $this->seedRequest(qualified: true);
    $order = new InterventionRecord();
    $order->id = 'ad69061e-b12f-41de-8a66-000000000070';
    $order->organization = $this->organization(self::OTHER_ORG);
    $order->name = 'Foreign corrective draft';
    $order->type = 'corrective_maintenance';
    $order->number = 1;
    $order->createdAt = $order->updatedAt = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    $this->main()->persist($order);
    $this->main()->flush();
    $this->request($client, 'POST', '/' . self::REQUEST . '/convert', ['clientOperationId' => self::OPERATION, 'existingInterventionId' => $order->id], 2);
    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertNull($this->repository()->conversionReceiptForRequest(self::REQUEST, self::ORG));
  }

  /**
   * @param list<string> $permissions
   */
  private function client(string $actor = self::OWNER, array $permissions = ['*']): KernelBrowser
  {
    $client = static::createClient();
    $organization = $this->organization(self::ORG);
    $now = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    foreach ([self::OWNER => 'ad69061e-b12f-41de-8a66-000000000004', self::MEMBER => 'ad69061e-b12f-41de-8a66-000000000005'] as $userId => $memberId) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $organization;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $this->main()->persist($member);
      if (self::OWNER === $userId) {
        $role = new OrganizationRoleRecord();
        $role->id = 'ad69061e-b12f-41de-8a66-000000000006';
        $role->organization = $organization;
        $role->name = 'service_request_test';
        $role->permissions = $permissions;
        $role->isSystem = false;
        $role->createdAt = $now;
        $this->main()->persist($role);
        $assignment = new OrganizationMemberRoleRecord();
        $assignment->member = $member;
        $assignment->role = $role;
        $assignment->assignedAt = $now;
        $this->main()->persist($assignment);
      }
    }
    $this->main()->flush();
    $client->loginUser(new SecurityUser($actor, $actor . '@example.com', 'password', ['ROLE_USER']), 'api');

    return $client;
  }

  private function organization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Repair requests';
    $organization->slug = $id;
    $organization->ownerUserId = self::OWNER;
    $organization->createdByUserId = self::OWNER;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $organization->updatedAt = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    $this->main()->persist($organization);
    $this->main()->flush();

    return $organization;
  }

  private function seedTarget(bool $customer = true): EquipmentRecord
  {
    $now = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    if ($customer) {
      $client = new CustomerRecord();
      $client->id = self::CUSTOMER;
      $client->organizationId = self::ORG;
      $client->name = 'Internal client';
      $client->contacts = [['name' => 'Private contact', 'email' => 'private@example.com', 'phone' => null, 'role' => null]];
      $client->createdAt = $client->updatedAt = $now;
      $this->main()->persist($client);
    }
    $site = new FacilityRecord();
    $site->id = self::SITE;
    $site->organization = $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $site->type = 'site';
    $site->name = 'Fire site';
    $site->customerId = $customer ? self::CUSTOMER : null;
    $site->createdAt = $site->updatedAt = $now;
    $this->main()->persist($site);
    $building = new FacilityRecord();
    $building->id = self::BUILDING;
    $building->organization = $site->organization;
    $building->type = 'building';
    $building->name = 'Building A';
    $building->parentFacility = $site;
    $building->createdAt = $building->updatedAt = $now;
    $this->main()->persist($building);
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organization = $site->organization;
    $equipment->facilityId = $building->id;
    $equipment->type = 'fire_extinguisher';
    $equipment->name = 'Extinguisher A';
    $equipment->assetCode = 'EXT-A';
    $equipment->status = 'in_service';
    $equipment->createdAt = $equipment->updatedAt = $now;
    $this->main()->persist($equipment);
    $this->main()->flush();

    return $equipment;
  }

  /**
   * @param array<string,mixed> $extraSnapshot
   */
  private function seedRequest(string $id = self::REQUEST, string $organizationId = self::ORG, bool $qualified = false, ?string $equipmentId = self::EQUIPMENT, array $extraSnapshot = []): ServiceRequest
  {
    $now = new DateTimeImmutable('2026-10-05T10:00:00+00:00');
    $snapshot = $extraSnapshot + ['equipment' => null === $equipmentId ? null : ['id' => $equipmentId, 'name' => 'Extinguisher A', 'assetCode' => 'EXT-A', 'status' => 'in_service'], 'site' => ['id' => self::SITE, 'name' => 'Fire site'], 'customer' => ['id' => self::CUSTOMER, 'name' => 'Internal client']];
    $request = ServiceRequest::create($id, $organizationId, new ServiceRequestTarget($equipmentId, self::SITE, $snapshot, null, null), new ServiceRequestContent('Seal damaged', 'Replace the broken seal and verify tightness.', 'normal'), $now);
    if ($qualified) {
      $request = $request->qualify('Field repair accepted', $now->modify('+1 minute'));
    }
    $this->repository()->save($request);

    return $request;
  }

  private function convert(): ConvertServiceRequestResult
  {
    /** @var CommandBusPort $bus */ $bus = static::getContainer()->get(CommandBusPort::class);
    $result = $bus->dispatch(new ConvertServiceRequestCommand(self::OWNER, self::ORG, self::REQUEST, 2, self::OPERATION));
    self::assertInstanceOf(ConvertServiceRequestResult::class, $result);

    return $result;
  }

  private function repository(): ServiceRequestRepositoryPort
  {
    /** @var ServiceRequestRepositoryPort $repository */ $repository = static::getContainer()->get(ServiceRequestRepositoryPort::class);

    return $repository;
  }

  private function main(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $manager */ $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $manager;
  }

  /**
   * @return array<string,mixed>
   */
  private function createBody(): array
  {
    return ['equipmentId' => self::EQUIPMENT, 'title' => ' Seal damaged ', 'description' => 'Replace the broken seal and verify tightness.'];
  }

  /**
   * @param array<string,mixed> $body
   *
   * @return array<string,mixed>
   */
  private function request(KernelBrowser $client, string $method, string $path, array $body = [], ?int $revision = null, ?string $rawRevision = null): array
  {
    $headers = ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    $header = $rawRevision ?? (null === $revision ? null : '"revision-' . $revision . '"');
    if (null !== $header) {
      $headers['HTTP_IF_MATCH'] = $header;
    }
    $client->request($method, '/api/organizations/' . self::ORG . '/service-requests' . $path, server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
    /** @var array<string,mixed> $decoded */ $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
  }
}
