<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\ChecklistRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

use function json_decode;
use function json_encode;
use function sprintf;

/**
 * Class InspectionCreationApiTest
 *
 * Preserves creation validation and inspector attribution on every HTTP creation surface.
 *
 * @category FunctionalTests
 */
final class InspectionCreationApiTest extends WebTestCase
{
  // #region Constants
  /**
   * Constant ORGANIZATION_ID
   */
  private const string ORGANIZATION_ID = '810e8400-e29b-41d4-a716-446655498001';

  /**
   * Constant FOREIGN_ORGANIZATION_ID
   */
  private const string FOREIGN_ORGANIZATION_ID = '810e8400-e29b-41d4-a716-446655498002';

  /**
   * Constant ACTOR_ID
   */
  private const string ACTOR_ID = '810e8400-e29b-41d4-a716-446655498003';

  /**
   * Constant READER_ID
   */
  private const string READER_ID = '810e8400-e29b-41d4-a716-446655498004';

  /**
   * Constant EQUIPMENT_ID
   */
  private const string EQUIPMENT_ID = '810e8400-e29b-41d4-a716-446655498010';

  /**
   * Constant FACILITY_ID
   */
  private const string FACILITY_ID = '810e8400-e29b-41d4-a716-446655498011';

  /**
   * Constant CHECKLIST_ID
   */
  private const string CHECKLIST_ID = '810e8400-e29b-41d4-a716-446655498012';

  /**
   * Constant INSPECTION_ID
   */
  private const string INSPECTION_ID = '810e8400-e29b-41d4-a716-446655498020';

  /**
   * Constant UNKNOWN_ID
   */
  private const string UNKNOWN_ID = '810e8400-e29b-41d4-a716-4466554980ff';
  // #endregion

  // #region Methods
  /**
   * Method expectedReferenceFailuresRemainBadRequests
   *
   * @access public
   *
   * @param string $surface the creation route shape
   * @param string $failure the invalid referenced resource
   * @param string $message the preserved error message
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidReferences')]
  public function expectedReferenceFailuresRemainBadRequests(string $surface, string $failure, string $message): void
  {
    $client = static::createClient();
    $this->seed($failure);
    $this->loginAs($client, self::ACTOR_ID);
    $input = $this->input();
    if ('equipment missing' === $failure) {
      $input['equipmentId'] = self::UNKNOWN_ID;
    } elseif ('facility missing' === $failure) {
      $input['facilityId'] = self::UNKNOWN_ID;
    } elseif ('checklist missing' === $failure) {
      $input['checklistId'] = self::UNKNOWN_ID;
    }
    $this->createInspection($client, $surface, $input);

    self::assertSame(400, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame($message, $this->payload($client)['detail']);
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM inspections WHERE organization_id = ?', [self::ORGANIZATION_ID]));
  }

  /**
   * Method invalidReferences
   *
   * @access public
   *
   * @return iterable<string, array{string, string, string}> creation surfaces and exact refusals
   */
  public static function invalidReferences(): iterable
  {
    $cases = [
      'equipment missing' => sprintf('Equipment with ID "%s" not found.', self::UNKNOWN_ID),
      'equipment foreign' => sprintf('Equipment with ID "%s" not found.', self::EQUIPMENT_ID),
      'equipment decommissioned' => sprintf('Equipment with ID "%s" is decommissioned and cannot be inspected.', self::EQUIPMENT_ID),
      'facility missing' => sprintf('Equipment with ID "%s" is not assigned to facility "%s" and cannot be inspected there.', self::EQUIPMENT_ID, self::UNKNOWN_ID),
      'facility foreign' => sprintf('Facility with ID "%s" not found.', self::FACILITY_ID),
      'facility archived' => sprintf('Facility with ID "%s" is archived and cannot be used.', self::FACILITY_ID),
      'checklist missing' => sprintf('Checklist with ID "%s" not found.', self::UNKNOWN_ID),
      'checklist foreign' => sprintf('Checklist with ID "%s" not found.', self::CHECKLIST_ID),
      'checklist archived' => sprintf('Checklist with ID "%s" is archived and cannot be used.', self::CHECKLIST_ID),
    ];
    foreach (['organization post', 'canonical post', 'canonical put'] as $surface) {
      foreach ($cases as $failure => $message) {
        yield $surface . ' ' . $failure => [$surface, $failure, $message];
      }
    }
  }

  /**
   * Method defaultInspectorAttributionIsPreserved
   *
   * @access public
   *
   * @param string $surface the creation route shape
   * @param string $mode the omitted, null, empty or explicit user identity
   *
   * @return void
   */
  #[Test]
  #[DataProvider('inspectorIdentities')]
  public function defaultInspectorAttributionIsPreserved(string $surface, string $mode): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ACTOR_ID);
    $input = $this->input();
    if ('omitted' !== $mode) {
      $input['inspectorUserId'] = match ($mode) {
        'null' => null,
        'empty' => '',
        default => self::READER_ID,
      };
    }
    $this->createInspection($client, $surface, $input);

    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $expectedInspectorId = 'explicit' === $mode ? self::READER_ID : self::ACTOR_ID;
    $inspector = $this->payload($client)['inspector'];
    self::assertIsArray($inspector);
    self::assertSame($expectedInspectorId, $inspector['id']);
    self::assertSame($expectedInspectorId, $this->main()->getConnection()->fetchOne('SELECT inspector_user_id FROM inspections WHERE organization_id = ?', [self::ORGANIZATION_ID]));
  }

  /**
   * Method inspectorIdentities
   *
   * @access public
   *
   * @return iterable<string, array{string, string}> creation surfaces and inspector identities
   */
  public static function inspectorIdentities(): iterable
  {
    foreach (['organization post', 'canonical post', 'canonical put'] as $surface) {
      foreach (['omitted', 'null', 'empty', 'explicit'] as $mode) {
        yield $surface . ' ' . $mode => [$surface, $mode];
      }
    }
  }

  /**
   * Method authorizationPrecedesReferencedResourceValidation
   *
   * @access public
   *
   * @param string $surface the creation route shape
   * @param bool $foreignOrganization whether organization scope is hidden
   *
   * @return void
   */
  #[Test]
  #[DataProvider('deniedCreations')]
  public function authorizationPrecedesReferencedResourceValidation(string $surface, bool $foreignOrganization): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, $foreignOrganization ? self::ACTOR_ID : self::READER_ID);
    $input = $this->input();
    $input['equipmentId'] = self::UNKNOWN_ID;
    $this->createInspection($client, $surface, $input, $foreignOrganization ? self::FOREIGN_ORGANIZATION_ID : self::ORGANIZATION_ID);

    self::assertSame($foreignOrganization ? 404 : 403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame($foreignOrganization ? 'Organization not found.' : 'Missing organization.inspection.write permission.', $this->payload($client)['detail']);
  }

  /**
   * Method deniedCreations
   *
   * @access public
   *
   * @return iterable<string, array{string, bool}> creation surfaces and scope denials
   */
  public static function deniedCreations(): iterable
  {
    foreach (['organization post', 'canonical post', 'canonical put'] as $surface) {
      yield $surface . ' missing permission' => [$surface, false];
      yield $surface . ' hidden organization' => [$surface, true];
    }
  }

  /**
   * Method input
   *
   * @access private
   *
   * @return array<string, mixed> a valid creation payload
   */
  private function input(): array
  {
    return ['equipmentId' => self::EQUIPMENT_ID, 'facilityId' => self::FACILITY_ID, 'checklistId' => self::CHECKLIST_ID, 'result' => 'pass', 'performedAt' => '2026-10-11T10:00:00+00:00', 'inspectorType' => 'user', 'inspectorName' => 'Creation Inspector'];
  }

  /**
   * Method createInspection
   *
   * @access private
   *
   * @param KernelBrowser $client the authenticated browser
   * @param string $surface the creation route shape
   * @param array<string, mixed> $input the request payload
   * @param string $organizationId the target organization
   *
   * @return void
   */
  private function createInspection(KernelBrowser $client, string $surface, array $input, string $organizationId = self::ORGANIZATION_ID): void
  {
    $input['organization'] = '/api/organizations/' . $organizationId;
    $client->request('canonical put' === $surface ? 'PUT' : 'POST', match ($surface) {
      'canonical put' => '/api/inspections/' . self::INSPECTION_ID,
      'canonical post' => '/api/inspections',
      default => '/api/organizations/' . $organizationId . '/inspections',
    }, server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_NONE_MATCH' => '*'], content: (string) json_encode($input));
  }

  /**
   * Method payload
   *
   * @access private
   *
   * @param KernelBrowser $client the browser holding the response
   *
   * @return array<string, mixed> the decoded response
   */
  private function payload(KernelBrowser $client): array
  {
    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $client->getResponse()->getContent(), true);

    return $payload;
  }

  /**
   * Method loginAs
   *
   * @access private
   *
   * @param KernelBrowser $client the browser to authenticate
   * @param string $userId the caller identity
   *
   * @return void
   */
  private function loginAs(KernelBrowser $client, string $userId): void
  {
    $client->loginUser(new SecurityUser(id: $userId, email: 'creation@example.com', password: 'hashed-password', roles: ['ROLE_USER']), 'api');
  }

  /**
   * Method main
   *
   * @access private
   *
   * @return EntityManagerInterface the main entity manager
   */
  private function main(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $main */
    $main = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $main;
  }

  /**
   * Method seed
   *
   * @access private
   *
   * @param ?string $failure the invalid resource state to reproduce
   *
   * @return void
   */
  private function seed(?string $failure = null): void
  {
    $main = $this->main();
    $now = new DateTimeImmutable('2026-10-11T09:00:00+00:00');
    foreach ([self::ORGANIZATION_ID, self::FOREIGN_ORGANIZATION_ID] as $organizationId) {
      $organization = new OrganizationRecord();
      $organization->id = $organizationId;
      $organization->name = 'Inspection Creation ' . $organizationId;
      $organization->slug = 'inspection-creation-' . $organizationId;
      $organization->ownerUserId = self::ACTOR_ID;
      $organization->createdByUserId = self::ACTOR_ID;
      $organization->createdAt = $now;
      $organization->updatedAt = $now;
      $main->persist($organization);
      if (self::ORGANIZATION_ID !== $organizationId) {
        continue;
      }
      foreach ([self::ACTOR_ID => ['*'], self::READER_ID => ['organization.inspection.read']] as $userId => $permissions) {
        $role = new OrganizationRoleRecord();
        $role->id = '810e8400-e29b-41d4-a716-44665549804' . (self::ACTOR_ID === $userId ? '1' : '2');
        $role->organization = $organization;
        $role->name = self::ACTOR_ID === $userId ? 'creation-admin' : 'creation-reader';
        $role->permissions = $permissions;
        $role->description = 'Creation contract fixture';
        $role->isSystem = false;
        $role->createdAt = $now;
        $member = new OrganizationMemberRecord();
        $member->id = '810e8400-e29b-41d4-a716-44665549805' . (self::ACTOR_ID === $userId ? '1' : '2');
        $member->organization = $organization;
        $member->userId = $userId;
        $member->isActive = true;
        $member->joinedAt = $now;
        $assignment = new OrganizationMemberRoleRecord();
        $assignment->member = $member;
        $assignment->role = $role;
        $assignment->assignedAt = $now;
        $main->persist($role);
        $main->persist($member);
        $main->persist($assignment);
      }
    }
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organizationId = 'facility foreign' === $failure ? self::FOREIGN_ORGANIZATION_ID : self::ORGANIZATION_ID;
    $facility->name = 'Creation site';
    $facility->type = 'site';
    $facility->status = 'facility archived' === $failure ? 'archived' : 'active';
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organizationId = 'equipment foreign' === $failure ? self::FOREIGN_ORGANIZATION_ID : self::ORGANIZATION_ID;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'equipment decommissioned' === $failure ? 'decommissioned' : 'operational';
    $equipment->facilityId = self::FACILITY_ID;
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $checklist = new ChecklistRecord();
    $checklist->id = self::CHECKLIST_ID;
    $checklist->organizationId = 'checklist foreign' === $failure ? self::FOREIGN_ORGANIZATION_ID : self::ORGANIZATION_ID;
    $checklist->name = 'Creation checklist';
    $checklist->version = '1';
    $checklist->status = 'checklist archived' === $failure ? 'archived' : 'active';
    $checklist->createdAt = $now;
    $checklist->updatedAt = $now;
    $main->persist($facility);
    $main->persist($equipment);
    $main->persist($checklist);
    $main->flush();
    $main->clear();
  }
  // #endregion
}
