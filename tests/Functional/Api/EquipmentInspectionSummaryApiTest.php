<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class EquipmentInspectionSummaryApiTest
 *
 * PostgreSQL facts remain scoped and independent from declared operational state.
 *
 * @category FunctionalTest
 */
final class EquipmentInspectionSummaryApiTest extends WebTestCase
{
  private const string ORG = '550e8400-e29b-41d4-a716-448050000001';

  private const string USER = '550e8400-e29b-41d4-a716-448050000002';

  private const string EQUIPMENT = '550e8400-e29b-41d4-a716-448050000010';

  #[Test]
  public function returnsLatestClosedPublishedControlAndRetainsIndependentOpenFindings(): void
  {
    $client = $this->client();
    $equipment = $this->seedEquipment();
    $old = $this->seedInspection('550e8400-e29b-41d4-a716-448050000020', 'closed', 'published', 'fail', '2026-10-03T08:00:00Z');
    $last = $this->seedInspection('550e8400-e29b-41d4-a716-448050000021', 'closed', 'published', 'pass', '2026-10-04T09:30:00Z');
    $submitted = $this->seedInspection('550e8400-e29b-41d4-a716-448050000022', 'submitted', 'published', 'partial', '2026-10-05T10:30:00Z');
    $draft = $this->seedInspection('550e8400-e29b-41d4-a716-448050000023', 'closed', 'draft', 'fail', '2026-10-06T11:00:00Z');
    $this->finding($old, '550e8400-e29b-41d4-a716-448050000030', 'high', 'open');
    $this->finding($old, '550e8400-e29b-41d4-a716-448050000031', 'critical', 'in_progress');
    $this->finding($last, '550e8400-e29b-41d4-a716-448050000032', 'medium', 'resolved');
    $this->finding($draft, '550e8400-e29b-41d4-a716-448050000033', 'critical', 'open');
    $other = $this->seedInspection('550e8400-e29b-41d4-a716-448050000024', 'closed', 'published', 'fail', '2026-10-06T12:00:00Z', '550e8400-e29b-41d4-a716-448050000011');
    $this->finding($other, '550e8400-e29b-41d4-a716-448050000034', 'low', 'open');
    $body = $this->summary($client);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(self::EQUIPMENT, $body['equipmentId']);
    self::assertSame(2, $body['openAnomalies']);
    self::assertSame(['low' => 0, 'medium' => 0, 'high' => 1, 'critical' => 1], $body['bySeverity']);
    self::assertSame($last->id, $body['lastInspectionId']);
    self::assertSame('2026-10-04T09:30:00+00:00', $body['lastInspectionPerformedAt']);
    self::assertSame('pass', $body['lastInspectionResult']);
    self::assertSame('under_maintenance', $equipment->status);
    self::assertNotSame($submitted->id, $body['lastInspectionId']);
  }

  #[Test]
  public function noRecordedControlHasExplicitNullFacts(): void
  {
    $client = $this->client();
    $this->seedEquipment();
    $body = $this->summary($client);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(0, $body['openAnomalies']);
    self::assertNull($body['lastInspectionId']);
    self::assertNull($body['lastInspectionPerformedAt']);
    self::assertNull($body['lastInspectionResult']);
  }

  #[Test]
  public function missingInspectionReadIsDenied(): void
  {
    $client = $this->client(['organization.equipment.read']);
    $this->seedEquipment();
    $this->summary($client);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function missingEquipmentReadIsDenied(): void
  {
    $client = $this->client(['organization.inspection.read']);
    $this->seedEquipment();
    $this->summary($client);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function outsideOrganizationIsIndistinguishableFromAbsentResource(): void
  {
    $client = $this->client(actor: '550e8400-e29b-41d4-a716-448050000099');
    $this->seedEquipment();
    $this->summary($client);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function unknownEquipmentIsNotFound(): void
  {
    $client = $this->client();
    $this->summary($client);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function equipmentFromAnotherOrganizationIsNotFound(): void
  {
    $client = $this->client();
    $foreign = $this->organization('550e8400-e29b-41d4-a716-448050000098');
    $this->seedEquipment($foreign);
    $this->summary($client);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function sameEquipmentIdentifierInForeignInspectionCannotContaminateFacts(): void
  {
    $client = $this->client();
    $this->seedEquipment();
    $foreign = $this->organization('550e8400-e29b-41d4-a716-448050000098');
    $inspection = $this->seedInspection('550e8400-e29b-41d4-a716-448050000020', 'closed', 'published', 'fail', '2026-10-05T11:00:00Z', organization: $foreign);
    $this->finding($inspection, '550e8400-e29b-41d4-a716-448050000030', 'critical', 'open');
    $body = $this->summary($client);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(0, $body['openAnomalies']);
    self::assertNull($body['lastInspectionId']);
  }

  #[Test]
  public function privateEquipmentDraftIsNotExposedByPublishedDossier(): void
  {
    $client = $this->client();
    $equipment = $this->seedEquipment();
    $equipment->recordStatus = 'draft';
    $equipment->interventionId = '550e8400-e29b-41d4-a716-448050000090';
    $this->main()->flush();
    $this->summary($client);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  /**
   * @param list<string> $permissions
   */
  private function client(array $permissions = ['organization.equipment.read', 'organization.inspection.read'], string $actor = self::USER): KernelBrowser
  {
    $client = static::createClient();
    $org = $this->organization(self::ORG);
    $member = new OrganizationMemberRecord();
    $member->id = '550e8400-e29b-41d4-a716-448050000003';
    $member->organization = $org;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = new DateTimeImmutable();
    $this->main()->persist($member);
    $role = new OrganizationRoleRecord();
    $role->id = '550e8400-e29b-41d4-a716-448050000004';
    $role->organization = $org;
    $role->name = 'summary_reader';
    $role->permissions = $permissions;
    $role->isSystem = false;
    $role->createdAt = new DateTimeImmutable();
    $this->main()->persist($role);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = new DateTimeImmutable();
    $this->main()->persist($assignment);
    $this->main()->flush();
    $client->loginUser(new SecurityUser($actor, $actor . '@example.com', 'password', ['ROLE_USER']), 'api');

    return $client;
  }

  private function main(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $em */ $em = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $em;
  }

  private function organization(string $id): OrganizationRecord
  {
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Inspection Summary';
    $organization->slug = 'inspection-summary-' . $id;
    $organization->ownerUserId = self::USER;
    $organization->createdByUserId = self::USER;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $this->main()->persist($organization);
    $this->main()->flush();

    return $organization;
  }

  private function seedEquipment(?OrganizationRecord $organization = null): EquipmentRecord
  {
    $now = new DateTimeImmutable();
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organization = $organization ?? $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'under_maintenance';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $this->main()->persist($equipment);
    $this->main()->flush();

    return $equipment;
  }

  private function seedInspection(string $id, string $status, string $recordStatus, string $result, string $performedAt, string $equipmentId = self::EQUIPMENT, ?OrganizationRecord $organization = null): InspectionRecord
  {
    $now = new DateTimeImmutable();
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization ?? $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $inspection->equipmentId = $equipmentId;
    $inspection->inspectorType = 'internal';
    $inspection->inspectorName = 'Inspector';
    $inspection->status = $status;
    $inspection->recordStatus = $recordStatus;
    $inspection->result = $result;
    $inspection->performedAt = new DateTimeImmutable($performedAt);
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $this->main()->persist($inspection);
    $this->main()->flush();

    return $inspection;
  }

  private function finding(InspectionRecord $inspection, string $id, string $severity, string $status): void
  {
    $now = new DateTimeImmutable();
    $finding = new NonConformityRecord();
    $finding->id = $id;
    $finding->inspection = $inspection;
    $finding->description = 'Recorded defect';
    $finding->severity = $severity;
    $finding->status = $status;
    $finding->createdAt = $now;
    $finding->updatedAt = $now;
    $this->main()->persist($finding);
    $this->main()->flush();
  }

  /**
   * @return array<string,mixed>
   */
  private function summary(KernelBrowser $client): array
  {
    $client->request('GET', '/api/organizations/' . self::ORG . '/equipment/' . self::EQUIPMENT . '/inspection-summary', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    /** @var array<string,mixed> $body */ $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $body;
  }
}
