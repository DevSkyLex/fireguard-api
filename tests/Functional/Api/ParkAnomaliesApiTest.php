<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * PostgreSQL contracts for the parc's published unresolved anomaly queue.
 *
 * @category Functional Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ParkAnomaliesApiTest extends WebTestCase
{
  #[Test]
  public function countersAndPaginatedRowsShareCustomerFamilyAndSubtree(): void
  {
    $client = $this->client();
    $scope = '?family=fire&customerId=' . self::id(10) . '&facilityId=' . self::id(20);
    $summary = $this->get($client, '/park-anomalies-summary' . $scope);
    self::assertSame(3, $summary['openAnomalies']);
    self::assertSame(['low' => 1, 'medium' => 1, 'high' => 1, 'critical' => 0], $summary['bySeverity']);
    $rows = $this->get($client, '/park-anomalies' . $scope . '&itemsPerPage=1&page=2');
    self::assertIsArray($rows['member']);
    self::assertIsArray($rows['member'][0]);
    self::assertSame($summary['openAnomalies'], $rows['totalItems']);
    self::assertCount(1, $rows['member']);
    self::assertContains($rows['member'][0]['inspectionId'], [self::id(100), self::id(101)]);
    self::assertContains($rows['member'][0]['equipmentId'], [self::id(30), self::id(31)]);
    self::assertContains($rows['member'][0]['status'], ['open', 'in_progress']);
    $safety = $this->get($client, '/park-anomalies-summary?family=safety&facilityId=' . self::id(20));
    self::assertIsArray($safety['bySeverity']);
    self::assertSame(1, $safety['openAnomalies']);
    self::assertSame(1, $safety['bySeverity']['critical']);
  }

  #[Test]
  public function aChildOnlyAnomalyIsExcludedFromDirectScopeAndRetainsFamilyAndCustomer(): void
  {
    $client = $this->client();
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    foreach ([200, 201] as $number) {
      $finding = $em->find(NonConformityRecord::class, self::id($number));
      self::assertInstanceOf(NonConformityRecord::class, $finding);
      $finding->status = 'done';
    }
    $em->flush();
    $scope = '?family=fire&customerId=' . self::id(10) . '&facilityId=' . self::id(20);
    $direct = $this->get($client, '/park-anomalies-summary' . $scope . '&includeDescendants=false');
    self::assertSame(0, $direct['openAnomalies']);
    self::assertSame(['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0], $direct['bySeverity']);
    $directRows = $this->get($client, '/park-anomalies' . $scope . '&includeDescendants=false');
    self::assertSame(0, $directRows['totalItems']);
    self::assertSame([], $directRows['member']);
    foreach (['', '&includeDescendants=true'] as $descendants) {
      $subtree = $this->get($client, '/park-anomalies-summary' . $scope . $descendants);
      $rows = $this->get($client, '/park-anomalies' . $scope . $descendants . '&itemsPerPage=1');
      self::assertSame(1, $subtree['openAnomalies']);
      self::assertSame($subtree['openAnomalies'], $rows['totalItems']);
      self::assertIsArray($rows['member']);
      self::assertCount(1, $rows['member']);
      self::assertIsArray($rows['member'][0]);
      self::assertSame(self::id(31), $rows['member'][0]['equipmentId']);
      self::assertSame(self::id(203), $rows['member'][0]['id']);
    }
    $otherCustomer = '?family=fire&customerId=' . self::id(11) . '&facilityId=' . self::id(20) . '&includeDescendants=true';
    self::assertSame(0, $this->get($client, '/park-anomalies-summary' . $otherCustomer)['openAnomalies']);
    self::assertSame(0, $this->get($client, '/park-anomalies' . $otherCustomer)['totalItems']);
  }

  #[Test]
  public function noFamilyDefaultIsImposedAndDraftOrForeignFactsAreExcluded(): void
  {
    $client = $this->client();
    $summary = $this->get($client, '/park-anomalies-summary');
    self::assertSame(5, $summary['openAnomalies']);
    self::assertSame(['low' => 2, 'medium' => 1, 'high' => 1, 'critical' => 1], $summary['bySeverity']);
    $rows = $this->get($client, '/park-anomalies?itemsPerPage=100');
    self::assertIsArray($rows['member']);
    self::assertSame(5, $rows['totalItems']);
    self::assertCount(5, $rows['member']);
    $empty = $this->get($client, '/park-anomalies-summary?customerId=' . self::id(11));
    self::assertSame(0, $empty['openAnomalies']);
  }

  #[Test]
  public function inspectionCollectionsUseTheSameFamilyCustomerAndSubtreeBeforeCount(): void
  {
    $client = $this->client();
    $filters = '?family=fire&customerId=' . self::id(10);
    $org = $this->get($client, '/inspections' . $filters . '&itemsPerPage=1');
    self::assertIsArray($org['member']);
    self::assertSame(2, $org['totalItems']);
    self::assertCount(1, $org['member']);
    $direct = $this->get($client, '/facilities/' . self::id(20) . '/inspections' . $filters);
    self::assertSame(1, $direct['totalItems']);
    $subtree = $this->get($client, '/facilities/' . self::id(20) . '/inspections' . $filters . '&includeDescendants=true');
    self::assertSame(2, $subtree['totalItems']);
  }

  #[Test]
  public function eitherMissingReadPermissionIsForbidden(): void
  {
    $client = $this->client(['organization.inspection.read']);
    $this->get($client, '/park-anomalies-summary', 403);
    $this->get($client, '/park-anomalies', 403);
  }

  #[Test]
  public function equipmentPermissionAloneDoesNotRevealAnomalies(): void
  {
    $client = $this->client(['organization.equipment.read']);
    $this->get($client, '/park-anomalies-summary', 403);
    $this->get($client, '/park-anomalies', 403);
  }

  #[Test]
  public function unknownAndForeignExplicitScopesAreEquallyHidden(): void
  {
    $client = $this->client();
    foreach (['customerId=' . self::id(901), 'customerId=' . self::id(910), 'facilityId=' . self::id(902), 'facilityId=' . self::id(920)] as $filter) {
      $this->get($client, '/park-anomalies-summary?' . $filter, 404);
      $this->get($client, '/park-anomalies?' . $filter, 404);
      $this->get($client, '/park-anomalies-summary?' . $filter . '&includeDescendants=false', 404);
      $this->get($client, '/park-anomalies?' . $filter . '&includeDescendants=false', 404);
    }
    $client->request('GET', '/api/organizations/' . self::id(900) . '/park-anomalies-summary');
    self::assertResponseStatusCodeSame(404);
  }

  #[Test]
  public function authenticationIsRequiredForBothProjections(): void
  {
    $client = static::createClient();
    foreach (['park-anomalies', 'park-anomalies-summary'] as $endpoint) {
      $client->request('GET', '/api/organizations/' . self::id(1) . '/' . $endpoint);
      self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }
  }

  /**
   * @return array<string,mixed>
   */
  private function get(KernelBrowser $client, string $suffix, int $status = 200): array
  {
    $client->request('GET', '/api/organizations/' . self::id(1) . $suffix, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame($status, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $body = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    $object = [];
    foreach ($body as $key => $value) {
      self::assertIsString($key);
      $object[$key] = $value;
    }

    return $object;
  }

  /**
   * @return non-empty-string
   */
  private static function id(int $number): string
  {
    return sprintf('980e8400-e29b-41d4-a716-44665548%04d', $number);
  }

  /**
   * @param list<string> $permissions
   */
  private function client(array $permissions = ['organization.inspection.read', 'organization.equipment.read']): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $this->seed($permissions);
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (UserId $id) => UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    static::getContainer()->set(UserRepositoryPort::class, $users);
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(static::getContainer(), self::id(2), 'parc@example.com'));

    return $client;
  }

  /**
   * @param list<string> $permissions
   */
  private function seed(array $permissions): void
  {
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable('2026-10-06T12:00:00+00:00');
    $orgs = [];
    foreach ([1, 900] as $number) {
      $org = new OrganizationRecord();
      $org->id = self::id($number);
      $org->name = 'Parc ' . $number;
      $org->slug = 'parc-scope-' . $number;
      $org->ownerUserId = $org->createdByUserId = self::id(999);
      $org->status = 'active';
      $org->isActive = true;
      $org->createdAt = $org->updatedAt = $now;
      $em->persist($org);
      $orgs[$number] = $org;
    }
    $role = new OrganizationRoleRecord();
    $role->id = self::id(3);
    $role->organization = $orgs[1];
    $role->name = 'parc-reader';
    $role->permissions = $permissions;
    $role->isSystem = false;
    $role->createdAt = $now;
    $em->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = self::id(4);
    $member->organization = $orgs[1];
    $member->userId = self::id(2);
    $member->isActive = true;
    $member->joinedAt = $now;
    $em->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $em->persist($assignment);
    foreach ([10, 11, 910] as $number) {
      $customer = new CustomerRecord();
      $customer->id = self::id($number);
      $customer->organizationId = $orgs[910 === $number ? 900 : 1]->id;
      $customer->name = 'Customer ' . $number;
      $customer->createdAt = $customer->updatedAt = $now;
      $em->persist($customer);
    }
    $facilities = [];
    foreach ([20, 21, 22, 920] as $number) {
      $facility = new FacilityRecord();
      $facility->id = self::id($number);
      $facility->organization = $orgs[920 === $number ? 900 : 1];
      $facility->type = 21 === $number ? 'building' : 'site';
      $facility->name = 'Place ' . $number;
      $facility->status = 'active';
      $facility->customerId = 20 === $number ? self::id(10) : null;
      $facility->parentFacility = 21 === $number ? $facilities[20] : null;
      $facility->createdAt = $facility->updatedAt = $now;
      $em->persist($facility);
      $facilities[$number] = $facility;
    }
    foreach ([30 => 20, 31 => 21, 32 => 21, 33 => 22, 34 => 20] as $number => $facilityNumber) {
      $equipment = new EquipmentRecord();
      $equipment->id = self::id($number);
      $equipment->organization = $orgs[1];
      $equipment->facilityId = self::id($facilityNumber);
      $equipment->type = 32 === $number ? 'camera' : 'fire_extinguisher';
      $equipment->serialNumber = 'PARC-' . $number;
      $equipment->status = 'operational';
      $equipment->recordStatus = 34 === $number ? 'draft' : 'published';
      $equipment->createdAt = $equipment->updatedAt = $now;
      $em->persist($equipment);
    }
    $inspections = [];
    foreach ([100 => [30, 20], 101 => [31, 21], 102 => [32, 21], 103 => [33, 22], 104 => [34, 20], 105 => [31, 21], 106 => [31, 21]] as $number => [$equipmentNumber, $facilityNumber]) {
      $inspection = new InspectionRecord();
      $inspection->id = self::id($number);
      $inspection->organization = $orgs[106 === $number ? 900 : 1];
      $inspection->equipmentId = self::id($equipmentNumber);
      $inspection->facilityId = self::id($facilityNumber);
      $inspection->inspectorType = 'user';
      $inspection->inspectorName = 'Technician';
      $inspection->inspectorUserId = self::id(2);
      $inspection->result = 'fail';
      $inspection->status = 'submitted';
      $inspection->recordStatus = 105 === $number ? 'draft' : 'published';
      $inspection->performedAt = $inspection->createdAt = $inspection->updatedAt = $now;
      $em->persist($inspection);
      $inspections[$number] = $inspection;
    }
    foreach ([200 => [100, 'low', 'open'], 201 => [100, 'medium', 'in_progress'], 202 => [100, 'critical', 'done'], 203 => [101, 'high', 'open'], 204 => [102, 'critical', 'open'], 205 => [103, 'low', 'open'], 206 => [104, 'critical', 'open'], 207 => [105, 'critical', 'open'], 208 => [106, 'critical', 'open']] as $number => [$inspectionNumber, $severity, $status]) {
      $nc = new NonConformityRecord();
      $nc->id = self::id($number);
      $nc->inspection = $inspections[$inspectionNumber];
      $nc->description = 'Issue ' . $number;
      $nc->severity = $severity;
      $nc->status = $status;
      $nc->createdAt = $nc->updatedAt = $now;
      $em->persist($nc);
    }
    $em->flush();
  }
}
