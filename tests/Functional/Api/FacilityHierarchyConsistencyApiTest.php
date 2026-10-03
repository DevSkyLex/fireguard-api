<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\DataFixtures\PlanFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Test FacilityHierarchyConsistencyApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityHierarchyConsistencyApiTest extends WebTestCase
{
  // #region Constants
  private const string ORG = 'f6100000-0000-4000-8000-000000000001';

  private const string USER = 'f6100000-0000-4000-8000-000000000002';

  private const string SITE = 'f6100000-0000-4000-8000-000000000003';

  private const string BUILDING = 'f6100000-0000-4000-8000-000000000004';

  private const string FLOOR = 'f6100000-0000-4000-8000-000000000005';

  private const string OTHER_SITE = 'f6100000-0000-4000-8000-000000000006';
  // #endregion

  // #region Tests
  /**
   * Method testOnlySitesCanBeCreatedAtTheRootAndExteriorAreasAreAllowed.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testOnlySitesCanBeCreatedAtTheRootAndExteriorAreasAreAllowed(): void
  {
    $this->seed();
    foreach (['building', 'floor', 'zone', 'area'] as $type) {
      $this->request('POST', $this->collection(), ['type' => $type, 'name' => 'Invalid root']);
      self::assertResponseStatusCodeSame(422);
    }
    $this->request('POST', $this->collection(), ['type' => 'area', 'name' => 'Outdoor area', 'parentFacilityId' => self::SITE]);
    self::assertResponseStatusCodeSame(201);
  }

  /**
   * Method testMoveRequiresTheCurrentRevisionAndUpdatesItOnSuccess.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testMoveRequiresTheCurrentRevisionAndUpdatesItOnSuccess(): void
  {
    $this->seed();
    $path = $this->collection() . '/' . self::BUILDING . '/move';
    $body = ['parentFacilityId' => self::OTHER_SITE];
    $this->request('POST', $path, $body);
    self::assertResponseStatusCodeSame(428);
    $this->request('POST', $path, $body, 2);
    self::assertResponseStatusCodeSame(412);
    $result = $this->request('POST', $path, $body, 1);
    self::assertResponseIsSuccessful();
    self::assertSame(self::OTHER_SITE, $result['parentFacilityId']);
    self::assertSame(2, $result['revision']);
  }

  /**
   * Method testATypeChangeCannotInvalidateItsChildren.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testATypeChangeCannotInvalidateItsChildren(): void
  {
    $this->seed();
    $this->request('PATCH', '/api/facilities/' . self::BUILDING, ['type' => 'zone'], 1);
    self::assertResponseStatusCodeSame(422);
    $result = $this->request('GET', $this->collection() . '/' . self::BUILDING);
    self::assertSame('building', $result['type']);
    self::assertSame(1, $result['revision']);
  }

  /**
   * Method testLegacyRootsAllowDescriptiveEditsAndExplicitRepair.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testLegacyRootsAllowDescriptiveEditsAndExplicitRepair(): void
  {
    $this->seed();
    $this->em()->getConnection()->executeStatement('UPDATE facilities SET parent_facility_id = NULL WHERE id = :id', ['id' => self::BUILDING]);
    $result = $this->request('PATCH', '/api/facilities/' . self::BUILDING, ['name' => 'Legacy building', 'parent' => null], 1);
    self::assertResponseIsSuccessful();
    self::assertSame(2, $result['revision']);
    $this->request('POST', $this->collection(), ['type' => 'floor', 'name' => 'Forbidden child', 'parentFacilityId' => self::BUILDING]);
    self::assertResponseStatusCodeSame(422);
    $result = $this->request('POST', $this->collection() . '/' . self::BUILDING . '/move', ['parentFacilityId' => self::SITE], 2);
    self::assertResponseIsSuccessful();
    self::assertSame(self::SITE, $result['parentFacilityId']);
  }

  /**
   * Method testPublishedChildrenCannotTargetAnInterventionDraft.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testPublishedChildrenCannotTargetAnInterventionDraft(): void
  {
    $this->seed();
    $this->em()->getConnection()->executeStatement("UPDATE facilities SET record_status = 'draft', intervention_id = :intervention WHERE id = :id", ['intervention' => self::USER, 'id' => self::OTHER_SITE]);
    $this->request('POST', $this->collection(), ['type' => 'building', 'name' => 'Published child', 'parentFacilityId' => self::OTHER_SITE]);
    self::assertResponseStatusCodeSame(422);
  }

  /**
   * Method testDuplicationRefusesInvalidEdgesAfterSkippingArchivedNodes.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testDuplicationRefusesInvalidEdgesAfterSkippingArchivedNodes(): void
  {
    $this->seed();
    $this->em()->getConnection()->executeStatement("UPDATE facilities SET status = 'archived' WHERE id = :id", ['id' => self::BUILDING]);
    $before = $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM facilities WHERE organization_id = :organization', ['organization' => self::ORG]);
    $this->request('POST', $this->collection() . '/' . self::SITE . '/duplicate', ['name' => 'Copy']);
    self::assertResponseStatusCodeSame(422);
    self::assertSame($before, $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM facilities WHERE organization_id = :organization', ['organization' => self::ORG]));
  }
  // #endregion

  // #region Helpers
  /**
   * Method request.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $body request body
   *
   * @return array<string, mixed> decoded response
   */
  private function request(string $method, string $path, array $body = [], ?int $revision = null): array
  {
    self::ensureKernelShutdown();
    $client = self::createClient();
    $client->loginUser(new SecurityUser(self::USER, 'hierarchy@example.test', 'unused', ['ROLE_USER']), 'api');
    $server = ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    if (null !== $revision) {
      $server['HTTP_IF_MATCH'] = '"revision-' . $revision . '"';
    }
    $client->request($method, $path, server: $server, content: json_encode($body, JSON_THROW_ON_ERROR));
    /** @var array<string, mixed> $result */
    $result = json_decode($client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

    return $result;
  }

  /**
   * Method collection.
   *
   * @since 1.0.0
   */
  private function collection(): string
  {
    return '/api/organizations/' . self::ORG . '/facilities';
  }

  /**
   * Method em.
   *
   * @since 1.0.0
   */
  private function em(): EntityManagerInterface
  {
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);

    return $em;
  }

  /**
   * Method seed.
   *
   * @since 1.0.0
   */
  private function seed(): void
  {
    self::createClient();
    $em = $this->em();
    $now = new DateTimeImmutable();
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Hierarchy';
    $org->slug = 'hierarchy-consistency';
    $org->ownerUserId = $org->createdByUserId = self::USER;
    $org->planId = PlanFixtures::MAX_PLAN_ID;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = $org->updatedAt = $now;
    $em->persist($org);
    $role = new OrganizationRoleRecord();
    $role->id = 'f6100000-0000-4000-8000-000000000007';
    $role->organization = $org;
    $role->name = 'Hierarchy admin';
    $role->permissions = ['*'];
    $role->isSystem = false;
    $role->createdAt = $now;
    $em->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = 'f6100000-0000-4000-8000-000000000008';
    $member->organization = $org;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = $now;
    $em->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $em->persist($assignment);
    $nodes = [];
    foreach ([[self::SITE, 'site', null], [self::OTHER_SITE, 'site', null], [self::BUILDING, 'building', self::SITE], [self::FLOOR, 'floor', self::BUILDING]] as [$id, $type, $parent]) {
      $node = new FacilityRecord();
      $node->id = $id;
      $node->organization = $org;
      $node->name = $type;
      $node->type = $type;
      $node->status = 'active';
      $node->parentFacility = null === $parent ? null : $nodes[$parent];
      $node->createdAt = $node->updatedAt = $now;
      $nodes[$id] = $node;
      $em->persist($node);
    }
    $em->flush();
  }
  // #endregion
}
