<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Port\Outbound\InterventionSiteCustomerSnapshotPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class CustomerApiTest
 *
 * Each HTTP test performs one request, preserving the suite's stateless test authentication.
 *
 * @category FunctionalTest
 */
final class CustomerApiTest extends WebTestCase
{
  private const string ORG = '550e8400-e29b-41d4-a716-448040000001';

  private const string OWNER = '550e8400-e29b-41d4-a716-448040000002';

  private const string MEMBER = '550e8400-e29b-41d4-a716-448040000003';

  private const string CUSTOMER = '550e8400-e29b-41d4-a716-448040000010';

  private const string SITE = '550e8400-e29b-41d4-a716-448040000020';

  #[Test]
  public function createsValidatedCustomer(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'POST', '/customers', ['name' => ' Building Owner ', 'code' => 'C-1', 'contacts' => [['name' => 'Pat', 'email' => 'pat@example.com']]]);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('Building Owner', $body['name']);
    self::assertSame(self::ORG, $body['organizationId']);
    self::assertSame(1, $body['revision']);
    self::assertIsString($body['id']);
  }

  #[Test]
  public function readsArchivedCustomer(): void
  {
    $client = $this->client();
    $this->seedCustomer(true);
    $body = $this->request($client, 'GET', '/customers/' . self::CUSTOMER);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(self::CUSTOMER, $body['id']);
    self::assertNotNull($body['archivedAt']);
  }

  #[Test]
  public function filtersArchivedCollectionAndMatchingTotal(): void
  {
    $client = $this->client();
    $this->seedCustomer(true);
    $this->seedCustomer(false, '550e8400-e29b-41d4-a716-448040000011');
    $body = $this->request($client, 'GET', '/customers?archived=true&search=Owner&itemsPerPage=1');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $body['totalItems']);
    /** @var list<array{id:string}> $members */
    $members = $body['member'];
    self::assertCount(1, $members);
    self::assertSame(self::CUSTOMER, $members[0]['id']);
  }

  #[Test]
  public function updatesNullableFieldsAtExpectedRevision(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $body = $this->request($client, 'PATCH', '/customers/' . self::CUSTOMER, ['code' => null], ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertNull($body['code']);
    self::assertSame('owner@example.com', $body['email']);
    self::assertSame(2, $body['revision']);
  }

  #[Test]
  public function archivesWithoutDeletingIdentity(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $body = $this->request($client, 'POST', '/customers/' . self::CUSTOMER . '/archive', extra: ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(self::CUSTOMER, $body['id']);
    self::assertNotNull($body['archivedAt']);
    self::assertSame(2, $body['revision']);
  }

  #[Test]
  public function restoresRetainedCustomer(): void
  {
    $client = $this->client();
    $this->seedCustomer(true);
    $body = $this->request($client, 'POST', '/customers/' . self::CUSTOMER . '/restore', extra: ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertNull($body['archivedAt']);
    self::assertSame(2, $body['revision']);
  }

  #[Test]
  public function requiresRevisionAfterCheckingAccess(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $this->request($client, 'PATCH', '/customers/' . self::CUSTOMER, ['name' => 'Changed']);
    self::assertSame(428, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function refusesStaleRevision(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $this->request($client, 'PATCH', '/customers/' . self::CUSTOMER, ['name' => 'Changed'], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(412, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function refusesDuplicateOrganizationCode(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $this->request($client, 'POST', '/customers', ['name' => 'Other', 'code' => 'CLIENT-1']);
    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function deniesUnentitledCollectionRead(): void
  {
    $client = $this->client(self::MEMBER);
    $this->request($client, 'GET', '/customers');
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function deniesUnentitledWrite(): void
  {
    $client = $this->client(self::MEMBER);
    $this->request($client, 'POST', '/customers', ['name' => 'Owner']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function hidesOutsideOrganizationRead(): void
  {
    $client = $this->client('550e8400-e29b-41d4-a716-448040000099');
    $this->request($client, 'GET', '/customers');
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function hidesOutsideOrganizationWrite(): void
  {
    $client = $this->client('550e8400-e29b-41d4-a716-448040000099');
    $this->request($client, 'POST', '/customers', ['name' => 'Owner']);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function createsSiteWithActiveCustomer(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $body = $this->request($client, 'POST', '/facilities', ['name' => 'Customer Site', 'type' => 'site', 'customerId' => self::CUSTOMER]);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(self::CUSTOMER, $body['customerId']);
  }

  #[Test]
  public function refusesCustomerOnChildFacility(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $this->seedSite();
    $this->request($client, 'POST', '/facilities', ['name' => 'Building', 'type' => 'building', 'parentFacilityId' => self::SITE, 'customerId' => self::CUSTOMER]);
    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function refusesNewArchivedCustomerAssignment(): void
  {
    $client = $this->client();
    $this->seedCustomer(true);
    $this->request($client, 'POST', '/facilities', ['name' => 'Customer Site', 'type' => 'site', 'customerId' => self::CUSTOMER]);
    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function refusesUnknownCustomerAssignment(): void
  {
    $client = $this->client();
    $this->request($client, 'POST', '/facilities', ['name' => 'Customer Site', 'type' => 'site', 'customerId' => self::CUSTOMER]);
    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function preservesArchivedCustomerOnSiteRead(): void
  {
    $client = $this->client();
    $this->seedCustomer(true);
    $this->seedSite(self::CUSTOMER);
    $body = $this->request($client, 'GET', '/facilities/' . self::SITE);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(self::CUSTOMER, $body['customerId']);
  }

  #[Test]
  public function siteCollectionFiltersCustomerAndTotalTogether(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $this->seedSite(self::CUSTOMER);
    $body = $this->request($client, 'GET', '/facilities?rootsOnly=true&customerId=' . self::CUSTOMER);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(1, $body['totalItems']);
    /** @var list<array{customerId:?string}> $members */
    $members = $body['member'];
    self::assertSame(self::CUSTOMER, $members[0]['customerId']);
  }

  #[Test]
  public function canonicalPatchAssignsActiveCustomerToRootSite(): void
  {
    $client = $this->client();
    $this->seedCustomer();
    $this->seedSite();
    $client->request('PATCH', '/api/facilities/' . self::SITE, server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-1"'], content: json_encode(['customerId' => self::CUSTOMER], JSON_THROW_ON_ERROR));
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    /** @var array<string,mixed> $body */
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertSame(self::CUSTOMER, $body['customerId']);
    self::assertSame(2, $body['revision']);
  }

  #[Test]
  public function unknownCustomerFilterIsNotFound(): void
  {
    $client = $this->client();
    $this->request($client, 'GET', '/facilities?rootsOnly=true&customerId=' . self::CUSTOMER);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function canonicalPatchKeepsUnchangedArchivedCustomerDuringDescriptiveEdit(): void
  {
    $client = $this->client();
    $this->seedCustomer(true);
    $this->seedSite(self::CUSTOMER);
    $client->request('PATCH', '/api/facilities/' . self::SITE, server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-1"'], content: json_encode(['customerId' => self::CUSTOMER, 'name' => 'Updated Site'], JSON_THROW_ON_ERROR));
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    /** @var array<string,mixed> $body */
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertSame(self::CUSTOMER, $body['customerId']);
    self::assertSame('Updated Site', $body['name']);
  }

  #[Test]
  public function closureSnapshotRetainsBuildingAndReadsCustomerFromRootSite(): void
  {
    $this->client();
    $this->seedCustomer(true);
    $site = $this->seedSite(self::CUSTOMER);
    $building = new FacilityRecord();
    $building->id = '550e8400-e29b-41d4-a716-448040000021';
    $building->organization = $site->organization;
    $building->parentFacility = $site;
    $building->type = 'building';
    $building->name = 'Selected Building';
    $building->createdAt = $site->createdAt;
    $building->updatedAt = $site->updatedAt;
    $this->main()->persist($building);
    $this->main()->flush();
    /** @var InterventionSiteCustomerSnapshotPort $snapshots */ $snapshots = static::getContainer()->get(InterventionSiteCustomerSnapshotPort::class);
    $snapshot = $snapshots->snapshot(self::ORG, $building->id);
    self::assertSame(['id' => $building->id, 'name' => 'Selected Building'], $snapshot['site']);
    self::assertNotNull($snapshot['customer']);
    self::assertSame(self::CUSTOMER, $snapshot['customer']['id']);
    self::assertSame('Owner', $snapshot['customer']['name']);
  }

  private function client(string $actor = self::OWNER): KernelBrowser
  {
    $client = static::createClient();
    $em = $this->main();
    $now = new DateTimeImmutable();
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Customer API';
    $org->slug = 'customer-api';
    $org->ownerUserId = self::OWNER;
    $org->createdByUserId = self::OWNER;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = $now;
    $org->updatedAt = $now;
    $em->persist($org);
    foreach ([self::OWNER => '550e8400-e29b-41d4-a716-448040000005', self::MEMBER => '550e8400-e29b-41d4-a716-448040000004'] as $userId => $memberId) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $org;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $em->persist($member);
      if (self::OWNER === $userId) {
        $role = new OrganizationRoleRecord();
        $role->id = '550e8400-e29b-41d4-a716-448040000006';
        $role->organization = $org;
        $role->name = 'customer_owner';
        $role->permissions = ['*'];
        $role->isSystem = false;
        $role->createdAt = $now;
        $em->persist($role);
        $assignment = new OrganizationMemberRoleRecord();
        $assignment->member = $member;
        $assignment->role = $role;
        $assignment->assignedAt = $now;
        $em->persist($assignment);
      }
    }
    $em->flush();
    $client->loginUser(new SecurityUser($actor, $actor . '@example.com', 'password', ['ROLE_USER']), 'api');

    return $client;
  }

  private function main(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $em */ $em = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $em;
  }

  private function seedCustomer(bool $archived = false, string $id = self::CUSTOMER): CustomerRecord
  {
    $now = new DateTimeImmutable();
    $customer = new CustomerRecord();
    $customer->id = $id;
    $customer->organizationId = self::ORG;
    $customer->name = 'Owner';
    $customer->code = self::CUSTOMER === $id ? 'CLIENT-1' : null;
    $customer->email = 'owner@example.com';
    $customer->createdAt = $now;
    $customer->updatedAt = $now;
    $customer->archivedAt = $archived ? $now : null;
    $this->main()->persist($customer);
    $this->main()->flush();

    return $customer;
  }

  private function seedSite(?string $customerId = null): FacilityRecord
  {
    $now = new DateTimeImmutable();
    $site = new FacilityRecord();
    $site->id = self::SITE;
    $site->organization = $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $site->customerId = $customerId;
    $site->type = 'site';
    $site->name = 'Customer Site';
    $site->createdAt = $now;
    $site->updatedAt = $now;
    $this->main()->persist($site);
    $this->main()->flush();

    return $site;
  }

  /**
   * @param array<string,mixed> $body
   * @param array<string,string> $extra
   *
   * @return array<string,mixed>
   */
  private function request(KernelBrowser $client, string $method, string $path, array $body = [], array $extra = []): array
  {
    $client->request($method, '/api/organizations/' . self::ORG . $path, server: ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'] + $extra, content: json_encode($body, JSON_THROW_ON_ERROR));
    /** @var array<string,mixed> $decoded */ $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
  }
}
