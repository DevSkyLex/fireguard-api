<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentRecord, EquipmentReplacementReceiptRecord};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class EquipmentReplacementApiTest
 *
 * Exercises the PostgreSQL replacement transaction, replay journal and organization denials.
 *
 * @category Functional Tests
 */
final class EquipmentReplacementApiTest extends WebTestCase
{
  // #region Constants
  private const string ORG = '770e8400-e29b-41d4-a716-446655491001';

  private const string USER = '770e8400-e29b-41d4-a716-446655491002';

  private const string SITE = '770e8400-e29b-41d4-a716-446655491003';

  private const string OLD = '770e8400-e29b-41d4-a716-446655491004';

  private const string STOCK = '770e8400-e29b-41d4-a716-446655491005';

  private const string OPERATION = '770e8400-e29b-41d4-a716-446655491006';
  // #endregion

  // #region Methods
  /**
   * Method replacesAndReplaysPreservingTheOriginalPlacementAndIdentity
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function replacesAndReplaysPreservingTheOriginalPlacementAndIdentity(): void
  {
    $client = $this->client();
    $payload = ['clientOperationId' => self::OPERATION, 'successorEquipmentId' => self::STOCK];
    $first = $this->replace($client, $payload);
    self::assertFalse($first['replayed']);
    self::assertSame(self::OLD, $first['predecessorEquipmentId']);
    self::assertSame(self::STOCK, $first['successorEquipmentId']);
    $replayed = $this->replace($client, $payload);
    self::assertTrue($replayed['replayed']);

    $manager = $this->manager();
    $manager->clear();
    $old = $manager->find(EquipmentRecord::class, self::OLD);
    $new = $manager->find(EquipmentRecord::class, self::STOCK);
    self::assertInstanceOf(EquipmentRecord::class, $old);
    self::assertInstanceOf(EquipmentRecord::class, $new);
    self::assertSame('decommissioned', $old->status);
    self::assertSame(self::STOCK, $old->successorEquipmentId);
    self::assertSame('OLD-SERIAL', $old->serialNumber);
    self::assertSame('Room A', $old->locationLabel);
    self::assertSame(self::SITE, $old->facilityId);
    self::assertSame(self::OLD, $new->predecessorEquipmentId);
    self::assertSame('operational', $new->status);
    self::assertSame(self::SITE, $new->facilityId);
    self::assertSame('Room A', $new->locationLabel);
    self::assertNotNull($new->commissionedAt);
    self::assertSame(1, $manager->getRepository(EquipmentReplacementReceiptRecord::class)->count(['organizationId' => self::ORG]));
  }

  /**
   * Method createsTheSuccessorAtomicallyAndReplaysWithoutCreatingAnother
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function createsTheSuccessorAtomicallyAndReplaysWithoutCreatingAnother(): void
  {
    $client = $this->client();
    $payload = [
      'clientOperationId' => self::OPERATION,
      'successor' => ['type' => 'fire_extinguisher', 'serialNumber' => 'CREATED-SERIAL', 'name' => 'New extinguisher', 'assetCode' => 'NEW-001', 'criticality' => 'high'],
    ];
    $first = $this->replace($client, $payload);
    $replayed = $this->replace($client, $payload);
    self::assertTrue($replayed['replayed']);
    self::assertSame($first['successorEquipmentId'], $replayed['successorEquipmentId']);
    self::assertSame(1, $this->manager()->getRepository(EquipmentRecord::class)->count(['serialNumber' => 'CREATED-SERIAL']));
    $new = $this->manager()->find(EquipmentRecord::class, $first['successorEquipmentId']);
    self::assertInstanceOf(EquipmentRecord::class, $new);
    self::assertSame(self::SITE, $new->facilityId);
    self::assertSame('operational', $new->status);
    self::assertSame('NEW-001', $new->assetCode);
  }

  /**
   * Method changedReplayPayloadConflictsWithoutChangingTheReceipt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function changedReplayPayloadConflictsWithoutChangingTheReceipt(): void
  {
    $client = $this->client();
    $this->replace($client, ['clientOperationId' => self::OPERATION, 'successorEquipmentId' => self::STOCK]);
    $this->replace($client, [
      'clientOperationId' => self::OPERATION, 'successor' => ['type' => 'fire_extinguisher'],
    ], 409);
    self::assertSame(2, $this->manager()->getRepository(EquipmentRecord::class)->count(['organization' => self::ORG]));
  }

  /**
   * Method rejectsAnAlreadyDeployedSuccessorWithoutRetiringTheOriginal
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function rejectsAnAlreadyDeployedSuccessorWithoutRetiringTheOriginal(): void
  {
    $client = $this->client();
    $new = $this->manager()->find(EquipmentRecord::class, self::STOCK);
    self::assertInstanceOf(EquipmentRecord::class, $new);
    $new->status = 'operational';
    $new->facilityId = self::SITE;
    $this->manager()->flush();
    $this->replace($client, ['clientOperationId' => self::OPERATION, 'successorEquipmentId' => self::STOCK], 409);
    $this->manager()->clear();
    $old = $this->manager()->find(EquipmentRecord::class, self::OLD);
    self::assertInstanceOf(EquipmentRecord::class, $old);
    self::assertSame('operational', $old->status);
    self::assertNull($old->successorEquipmentId);
    self::assertSame(0, $this->manager()->getRepository(EquipmentReplacementReceiptRecord::class)->count(['organizationId' => self::ORG]));
  }

  /**
   * Method invalidCreationRollsBackWithoutRetiringTheOriginal
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function invalidCreationRollsBackWithoutRetiringTheOriginal(): void
  {
    $client = $this->client();
    $this->replace($client, [
      'clientOperationId' => self::OPERATION,
      'successor' => ['type' => 'fire_extinguisher', 'serialNumber' => 'OLD-SERIAL'],
    ], 409);
    $this->manager()->clear();
    $old = $this->manager()->find(EquipmentRecord::class, self::OLD);
    self::assertInstanceOf(EquipmentRecord::class, $old);
    self::assertSame('operational', $old->status);
    self::assertNull($old->successorEquipmentId);
    self::assertSame(0, $this->manager()->getRepository(EquipmentReplacementReceiptRecord::class)->count(['organizationId' => self::ORG]));
  }

  /**
   * Method missingEquipmentAndForeignOrganizationAreHidden
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function missingEquipmentAndForeignOrganizationAreHidden(): void
  {
    $client = $this->client();
    $foreign = new OrganizationRecord();
    $foreign->id = '770e8400-e29b-41d4-a716-446655491099';
    $foreign->name = 'Foreign replacement organization';
    $foreign->slug = 'foreign-replacement-contract';
    $foreign->ownerUserId = self::USER;
    $foreign->createdByUserId = self::USER;
    $foreign->status = 'active';
    $foreign->isActive = true;
    $foreign->createdAt = $foreign->updatedAt = new DateTimeImmutable();
    $this->manager()->persist($foreign);
    $foreignAsset = new EquipmentRecord();
    $foreignAsset->id = '770e8400-e29b-41d4-a716-446655491098';
    $foreignAsset->organization = $foreign;
    $foreignAsset->type = 'fire_extinguisher';
    $foreignAsset->status = 'in_stock';
    $foreignAsset->createdAt = $foreignAsset->updatedAt = new DateTimeImmutable();
    $this->manager()->persist($foreignAsset);
    $this->manager()->flush();
    $this->replace($client, ['clientOperationId' => self::OPERATION, 'successorEquipmentId' => '770e8400-e29b-41d4-a716-446655491099'], 404);
    $this->replace($client, ['clientOperationId' => self::OPERATION, 'successorEquipmentId' => $foreignAsset->id], 404);
    $client->loginUser(new SecurityUser(self::USER, 'replacement@example.com', 'unused', ['ROLE_USER']), 'api');
    $client->request(
      'POST',
      '/api/organizations/770e8400-e29b-41d4-a716-446655491099/equipment/' . self::OLD . '/replace',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: json_encode(['clientOperationId' => self::OPERATION, 'successorEquipmentId' => self::STOCK], JSON_THROW_ON_ERROR),
    );
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  /**
   * Method deniesMembersWithoutWritePermission
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function deniesMembersWithoutWritePermission(): void
  {
    $client = $this->client(['organization.equipment.read']);
    $this->replace($client, ['clientOperationId' => self::OPERATION, 'successorEquipmentId' => self::STOCK], 403);
  }

  /**
   * Method validatesTheSuccessorChoiceBeforeDispatch
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function validatesTheSuccessorChoiceBeforeDispatch(): void
  {
    $client = $this->client();
    $this->replace($client, ['clientOperationId' => self::OPERATION], 422);
  }

  /**
   * Method aReceiptPersistenceFailureRollsBackTheNewAssetAndRetirement
   *
   * PostgreSQL rejects the final receipt after nested asset creation has succeeded.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function aReceiptPersistenceFailureRollsBackTheNewAssetAndRetirement(): void
  {
    $client = $this->client();
    $connection = $this->manager()->getConnection();
    $connection->executeStatement("CREATE FUNCTION reject_replacement_receipt() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'replacement receipt failure'; END; $$");
    $connection->executeStatement('CREATE TRIGGER reject_replacement_receipt BEFORE INSERT ON equipment_replacement_receipts FOR EACH ROW EXECUTE FUNCTION reject_replacement_receipt()');
    $this->replace($client, [
      'clientOperationId' => self::OPERATION,
      'successor' => ['type' => 'fire_extinguisher', 'serialNumber' => 'ROLLBACK-SERIAL'],
    ], 500);
    self::assertSame('operational', $connection->fetchOne('SELECT status FROM equipment WHERE id = :id', ['id' => self::OLD]));
    self::assertNull($connection->fetchOne('SELECT successor_equipment_id FROM equipment WHERE id = :id', ['id' => self::OLD]));
    $assetCount = $connection->fetchOne('SELECT COUNT(*) FROM equipment WHERE serial_number = :serial', ['serial' => 'ROLLBACK-SERIAL']);
    $receiptCount = $connection->fetchOne('SELECT COUNT(*) FROM equipment_replacement_receipts WHERE organization_id = :organization', ['organization' => self::ORG]);
    self::assertIsNumeric($assetCount);
    self::assertIsNumeric($receiptCount);
    self::assertSame(0, (int) $assetCount);
    self::assertSame(0, (int) $receiptCount);
  }

  /**
   * Method replace
   *
   * @access private
   *
   * @param KernelBrowser $client the API client
   * @param array<string, mixed> $payload the replacement body
   * @param int $status the expected response status
   *
   * @return array<string, mixed> decoded output
   */
  private function replace(KernelBrowser $client, array $payload, int $status = 200): array
  {
    $client->loginUser(new SecurityUser(self::USER, 'replacement@example.com', 'unused', ['ROLE_USER']), 'api');
    $client->request(
      'POST',
      '/api/organizations/' . self::ORG . '/equipment/' . self::OLD . '/replace',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: json_encode($payload, JSON_THROW_ON_ERROR),
    );
    self::assertSame($status, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $output = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($output);
    $object = [];
    foreach ($output as $key => $value) {
      self::assertIsString($key);
      $object[$key] = $value;
    }

    return $object;
  }

  /**
   * Method manager
   *
   * @access private
   *
   * @return EntityManagerInterface the main test manager
   */
  private function manager(): EntityManagerInterface
  {
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager;
  }

  /**
   * Method client
   *
   * @access private
   *
   * @param list<string> $permissions the test role permissions
   *
   * @return KernelBrowser the seeded authenticated client
   */
  private function client(array $permissions = ['*']): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $users = $this->createStub(\User\Application\Port\Outbound\UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (\User\Domain\ValueObject\UserId $id) => \Tests\Support\Factory\UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    static::getContainer()->set(\User\Application\Port\Outbound\UserRepositoryPort::class, $users);
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . \Tests\Support\Auth\InteractiveTokenFactory::issue(static::getContainer(), self::USER, 'replacement@example.com'));
    $now = new DateTimeImmutable();
    $manager = $this->manager();
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Replacement contract organization';
    $organization->slug = 'replacement-contract';
    $organization->ownerUserId = self::USER;
    $organization->createdByUserId = self::USER;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $organization->updatedAt = $now;
    $manager->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = '770e8400-e29b-41d4-a716-446655491007';
    $role->organization = $organization;
    $role->name = 'replacement-contract';
    $role->permissions = $permissions;
    $role->isSystem = false;
    $role->createdAt = $now;
    $manager->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = '770e8400-e29b-41d4-a716-446655491008';
    $member->organization = $organization;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = $now;
    $manager->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $manager->persist($assignment);
    $site = new FacilityRecord();
    $site->id = self::SITE;
    $site->organization = $organization;
    $site->name = 'Replacement site';
    $site->type = 'site';
    $site->status = 'active';
    $site->metadata = [];
    $site->createdAt = $site->updatedAt = $now;
    $manager->persist($site);
    foreach ([self::OLD => 'operational', self::STOCK => 'in_stock'] as $id => $status) {
      $equipment = new EquipmentRecord();
      $equipment->id = $id;
      $equipment->organization = $organization;
      $equipment->type = 'fire_extinguisher';
      $equipment->status = $status;
      $equipment->facilityId = self::OLD === $id ? self::SITE : null;
      $equipment->serialNumber = self::OLD === $id ? 'OLD-SERIAL' : 'STOCK-SERIAL';
      $equipment->locationLabel = self::OLD === $id ? 'Room A' : null;
      $equipment->createdAt = $equipment->updatedAt = $now;
      $manager->persist($equipment);
    }
    $manager->flush();
    $client->loginUser(new SecurityUser(self::USER, 'replacement@example.test', 'hashed', ['ROLE_USER']), 'api');

    return $client;
  }
  // #endregion
}
