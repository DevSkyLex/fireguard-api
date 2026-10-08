<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, InterventionDraftWorkItem};
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;

/** Real HTTP and PostgreSQL coverage of plan contracts, isolation and replay. */
final class MaintenancePlanApiTest extends WebTestCase
{
  private const string ORG = '780e8400-e29b-41d4-a716-446655440001';

  private const string EQUIPMENT = '780e8400-e29b-41d4-a716-446655440002';

  private const string ADMIN = '780e8400-e29b-41d4-a716-446655440003';

  private const string READER = '780e8400-e29b-41d4-a716-446655440004';

  private const string OUTSIDER = '780e8400-e29b-41d4-a716-446655440005';

  private ?string $loggedUserId = null;

  #[Test]
  public function abandonedOccurrenceWorkCannotBeDeletedAndRetriesKeepItsOriginalIdentity(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $input = $this->input('maintenance', 'P1M');
    $input['active'] = true;
    $plan = $this->request($client, 'POST', '/plans', $input);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    self::assertIsString($plan['id']);
    $this->request($client, 'POST', '/plans/activate');
    $first = $this->request($client, 'POST', '/plans/' . $plan['id'] . '/generate', []);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertIsString($first['interventionId']);
    $before = $this->request($client, 'GET', '/plans/' . $plan['id']);
    self::assertIsArray($before['openOccurrence']);
    $taskId = $this->main()->getConnection()->fetchOne('SELECT id FROM intervention_work_items WHERE intervention_id = :id', ['id' => $first['interventionId']]);
    self::assertIsString($taskId);
    $this->request($client, 'DELETE', '/api/intervention-work-items/' . $taskId, headers: ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $currentWork = $this->request($client, 'GET', '/api/interventions/' . $first['interventionId']);
    self::assertIsInt($currentWork['revision']);
    $abandoned = $this->request($client, 'PATCH', '/api/interventions/' . $first['interventionId'], ['status' => 'abandoned', 'responsible' => '780e8402' . substr(self::ADMIN, 8)], ['HTTP_IF_MATCH' => '"revision-' . $currentWork['revision'] . '"']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('abandoned', $abandoned['status']);
    self::assertIsInt($abandoned['revision']);
    $this->request($client, 'DELETE', '/api/interventions/' . $first['interventionId'], headers: ['HTTP_IF_MATCH' => '"revision-' . $abandoned['revision'] . '"']);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $replay = $this->request($client, 'POST', '/plans/' . $plan['id'] . '/generate', []);
    self::assertTrue($replay['replayed']);
    self::assertSame($first['interventionId'], $replay['interventionId']);
    $retry = $this->request($client, 'POST', '/plans/' . $plan['id'] . '/generate', ['retry' => true]);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame($first['occurrenceId'], $retry['occurrenceId']);
    self::assertIsString($retry['interventionId']);
    self::assertNotSame($first['interventionId'], $retry['interventionId']);
    $after = $this->request($client, 'GET', '/plans/' . $plan['id']);
    self::assertIsArray($after['openOccurrence']);
    self::assertSame($before['openOccurrence']['dueAt'], $after['openOccurrence']['dueAt']);
    self::assertSame(2, $after['openOccurrence']['attempt']);
    $newWork = $this->request($client, 'GET', '/api/interventions/' . $retry['interventionId']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame('draft', $newWork['status']);
    $this->request($client, 'DELETE', '/api/interventions/' . $first['interventionId'], headers: ['HTTP_IF_MATCH' => '"revision-' . $abandoned['revision'] . '"']);
    self::assertSame(409, $client->getResponse()->getStatusCode(), 'Previous task occurrence identities retain the old attempt after retry.');
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_occurrences WHERE organization_id = :org', ['org' => self::ORG]));
  }

  #[Test]
  public function preparePreviewEnableGenerateAndReplayKeepIndependentOperations(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $control = $this->request($client, 'POST', '/plans', $this->input('control', 'P1Y'));
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertFalse($control['active']);
    self::assertIsString($control['id']);
    self::assertSame('Europe/Paris', $control['calendarTimezone']);
    $maintenance = $this->request($client, 'POST', '/plans', $this->input('maintenance', 'P1M'));
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertNotSame($control['id'], $maintenance['id']);
    self::assertIsString($maintenance['id']);
    $preview = $this->request($client, 'GET', '/plans/' . $maintenance['id'] . '/preview');
    self::assertSame(['2027-01-31T00:00:00+01:00', '2027-02-28T00:00:00+01:00', '2027-03-31T00:00:00+02:00'], $preview['dates']);
    $this->request($client, 'PATCH', '/plans/' . $control['id'], ['active' => true]);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $this->request($client, 'PATCH', '/plans/' . $maintenance['id'], ['active' => true]);
    $this->request($client, 'POST', '/plans/activate');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $first = $this->request($client, 'POST', '/plans/' . $maintenance['id'] . '/generate', []);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $replay = $this->request($client, 'POST', '/plans/' . $maintenance['id'] . '/generate', []);
    self::assertTrue($replay['replayed']);
    self::assertSame($first['occurrenceId'], $replay['occurrenceId']);
    self::assertSame($first['interventionId'], $replay['interventionId']);
    self::assertSame($first['number'], $replay['number']);
    $connection = $this->main()->getConnection();
    self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM maintenance_occurrences WHERE organization_id = :org', ['org' => self::ORG]));
    self::assertSame('2027-01-30 23:00:00', $connection->fetchOne('SELECT next_due_at FROM maintenance_schedules WHERE organization_id = :org AND equipment_id = :equipment', ['org' => self::ORG, 'equipment' => self::EQUIPMENT]));
    self::assertSame('preventive_maintenance', $connection->fetchOne('SELECT type FROM interventions WHERE id = :id', ['id' => $first['interventionId']]));
    self::assertSame('maintenance', $connection->fetchOne('SELECT operation_kind FROM intervention_work_items WHERE intervention_id = :id', ['id' => $first['interventionId']]));
  }

  #[Test]
  public function archiveRetainsThePlanAndItsReadableHistory(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $plan = $this->request($client, 'POST', '/plans', $this->input('control', 'P1Y'));
    self::assertIsString($plan['id']);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $this->request($client, 'DELETE', '/plans/' . $plan['id']);
    self::assertSame(204, $client->getResponse()->getStatusCode());
    $saved = $this->request($client, 'GET', '/plans/' . $plan['id']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertFalse($saved['active']);
    self::assertNotNull($saved['archivedAt']);
  }

  #[Test]
  public function archivedHistoricalCandidatesStayExcludedWhenTheEngineIsActivated(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $this->seedLegacySchedule();
    $this->request($client, 'POST', '/plans/prepare-legacy');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $planId = $this->main()->getConnection()->fetchOne('SELECT id FROM maintenance_plans WHERE organization_id = :org', ['org' => self::ORG]);
    self::assertIsString($planId);
    $this->request($client, 'DELETE', '/plans/' . $planId);
    self::assertSame(204, $client->getResponse()->getStatusCode());
    $archived = $this->request($client, 'GET', '/plans/' . $planId);
    self::assertNotNull($archived['archivedAt']);
    $this->request($client, 'POST', '/plans/activate');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $saved = $this->request($client, 'GET', '/plans/' . $planId);
    self::assertSame($archived['archivedAt'], $saved['archivedAt']);
    self::assertFalse($saved['active']);
    $this->request($client, 'POST', '/plans/' . $planId . '/generate', []);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_occurrences WHERE organization_id = :org', ['org' => self::ORG]));
  }

  #[Test]
  public function aNameOnlyEditRetainsTheOpenOccurrenceAndRejectsCalendarChanges(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $input = $this->input('maintenance', 'P1M');
    $input['active'] = true;
    $plan = $this->request($client, 'POST', '/plans', $input);
    self::assertIsString($plan['id']);
    $this->request($client, 'POST', '/plans/activate');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $generated = $this->request($client, 'POST', '/plans/' . $plan['id'] . '/generate', []);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $renamed = $this->request($client, 'PATCH', '/plans/' . $plan['id'], ['name' => 'Renamed monthly servicing']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('Renamed monthly servicing', $renamed['name']);
    self::assertSame($plan['anchorAt'], $renamed['anchorAt']);
    self::assertSame($plan['nextDueAt'], $renamed['nextDueAt']);
    self::assertIsArray($renamed['openOccurrence']);
    self::assertSame($generated['occurrenceId'], $renamed['openOccurrence']['id']);
    self::assertSame('2027-01-31T00:00:00+01:00', $renamed['openOccurrence']['dueAt']);
    $this->request($client, 'PATCH', '/plans/' . $plan['id'], ['nextDueOn' => '2027-02-28']);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    $saved = $this->request($client, 'GET', '/plans/' . $plan['id']);
    self::assertIsArray($saved['openOccurrence']);
    foreach (['id', 'dueAt', 'attempt', 'interventionId', 'status', 'number', 'retryAllowed'] as $field) {
      self::assertSame($renamed['openOccurrence'][$field], $saved['openOccurrence'][$field]);
    }
    self::assertSame($plan['nextDueAt'], $saved['nextDueAt']);
  }

  #[Test]
  public function theHistoricalCampaignEndpointUsesReservationsAfterHandover(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $input = $this->input('control', 'P1Y');
    $input['anchorOn'] = '2026-10-01';
    $plan = $this->request($client, 'POST', '/plans', $input);
    self::assertIsString($plan['id']);
    $this->request($client, 'PATCH', '/plans/' . $plan['id'], ['active' => true]);
    $this->request($client, 'POST', '/plans/activate');
    $campaign = ['organization' => '/api/organizations/' . self::ORG, 'name' => 'Control campaign', 'dueBefore' => '2027-01-31T00:00:00Z'];
    $first = $this->request($client, 'POST', '/api/maintenance/campaigns', $campaign);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsString($first['interventionId']);
    $this->request($client, 'POST', '/api/maintenance/campaigns', $campaign);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_occurrences WHERE organization_id = :org', ['org' => self::ORG]));
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM interventions WHERE organization_id = :org', ['org' => self::ORG]));
  }

  #[Test]
  public function legacyWorkIsAttachedRatherThanRegeneratedAndKnownDatesStayExact(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $this->seedLegacySchedule();
    $factory = static::getContainer()->get(InterventionDraftFactoryPort::class);
    self::assertInstanceOf(InterventionDraftFactoryPort::class, $factory);
    $draft = $factory->create(new CreateInterventionDraftRequest(self::ORG, 'inspection_campaign', 'Prepared historical control', 'maintenance:campaign', workItems: [new InterventionDraftWorkItem('inspection', '/api/equipment/' . self::EQUIPMENT)], actorUserId: self::ADMIN));
    $this->request($client, 'POST', '/plans/activate');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $row = $this->main()->getConnection()->fetchAssociative('SELECT id, interval, next_due_at, last_completed_at FROM maintenance_plans WHERE organization_id = :org', ['org' => self::ORG]);
    self::assertIsArray($row);
    self::assertIsString($row['id']);
    self::assertSame('P6M', $row['interval']);
    self::assertSame('2027-03-01 00:00:00', $row['next_due_at']);
    self::assertSame('2026-09-01 00:00:00', $row['last_completed_at']);
    $replay = $this->request($client, 'POST', '/plans/' . $row['id'] . '/generate', []);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertTrue($replay['replayed']);
    self::assertSame($draft->interventionId, $replay['interventionId']);
    self::assertSame($draft->number, $replay['number']);
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM interventions WHERE organization_id = :org', ['org' => self::ORG]));
  }

  #[Test]
  public function ambiguousLegacyWorkRollsBackTheWholeEngineActivation(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $this->seedLegacySchedule();
    $factory = static::getContainer()->get(InterventionDraftFactoryPort::class);
    self::assertInstanceOf(InterventionDraftFactoryPort::class, $factory);
    foreach (['A', 'B'] as $name) {
      $factory->create(new CreateInterventionDraftRequest(self::ORG, 'inspection_campaign', 'Ambiguous control ' . $name, 'maintenance:campaign', workItems: [new InterventionDraftWorkItem('inspection', '/api/equipment/' . self::EQUIPMENT)], actorUserId: self::ADMIN));
    }
    $this->request($client, 'POST', '/plans/activate');
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $engine = $this->request($client, 'GET', '/engine');
    self::assertSame('legacy', $engine['mode']);
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_occurrences WHERE organization_id = :org', ['org' => self::ORG]));
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_plans WHERE organization_id = :org', ['org' => self::ORG]));
  }

  #[Test]
  public function editingTheHistoricalPlanUpdatesItsOverrideSourceRatherThanSilentlyResetting(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $this->seedLegacySchedule();
    $this->request($client, 'POST', '/plans/activate');
    $planId = $this->main()->getConnection()->fetchOne('SELECT id FROM maintenance_plans WHERE organization_id = :org', ['org' => self::ORG]);
    self::assertIsString($planId);
    $updated = $this->request($client, 'PATCH', '/plans/' . $planId, ['interval' => 'P1Y']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('P1Y', $updated['interval']);
    $reloaded = $this->request($client, 'GET', '/plans/' . $planId);
    self::assertSame('P1Y', $reloaded['interval']);
    self::assertSame('2027-09-01T00:00:00+00:00', $reloaded['nextDueAt']);
    self::assertSame('P1Y', $this->main()->getConnection()->fetchOne('SELECT interval_override FROM maintenance_schedules WHERE organization_id = :org', ['org' => self::ORG]));
  }

  #[Test]
  #[DataProvider('denials')]
  public function scopeAndPermissionDenials(string $user, string $method, string $path, int $status): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, $user);
    $this->request($client, $method, $path, $this->input('control', 'P1Y'));
    self::assertSame($status, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * @return iterable<string, array{string, string, string, int}>
   */
  public static function denials(): iterable
  {
    yield 'reader write' => [self::READER, 'POST', '/plans', 403];
    yield 'outside list' => [self::OUTSIDER, 'GET', '/plans', 404];
    yield 'outside write' => [self::OUTSIDER, 'POST', '/plans', 404];
    yield 'outside engine' => [self::OUTSIDER, 'GET', '/engine', 404];
  }

  #[Test]
  public function invalidCadenceAndUnknownEquipmentAreRejected(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $this->request($client, 'POST', '/plans', $this->input('control', 'P0M'));
    self::assertSame(422, $client->getResponse()->getStatusCode());
    $input = $this->input('control', 'P1Y');
    $input['equipmentId'] = self::OUTSIDER;
    $this->request($client, 'POST', '/plans', $input);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  /**
   * @param array<string, mixed>|null $body
   * @param array<string, string> $headers request preconditions
   *
   * @return array<string, mixed>
   */
  private function request(KernelBrowser &$client, string $method, string $path, ?array $body = null, array $headers = []): array
  {
    self::ensureKernelShutdown();
    $client = static::createClient();
    if (null !== $this->loggedUserId) {
      $this->login($client, $this->loggedUserId);
    }
    $url = str_starts_with($path, '/api/') ? $path : '/api/organizations/' . self::ORG . '/maintenance' . $path;
    $client->request($method, $url, server: $headers + ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: null === $body ? null : json_encode((object) $body, JSON_THROW_ON_ERROR));
    $content = (string) $client->getResponse()->getContent();

    if ('' === $content) {
      return [];
    }
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
  }

  /**
   * @return array<string, string>
   */
  private function input(string $kind, string $interval): array
  {
    return ['equipmentId' => self::EQUIPMENT, 'name' => $kind . ' operation', 'operationKind' => $kind, 'interval' => $interval, 'anchorOn' => '2027-01-31'];
  }

  private function main(): EntityManagerInterface
  {
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager;
  }

  private function login(KernelBrowser $client, string $id): void
  {
    $this->loggedUserId = $id;
    $client->disableReboot();
    $client->loginUser(new SecurityUser($id, 'plan-' . $id . '@example.com', 'hashed-password', ['ROLE_USER']), 'api');
  }

  private function seed(): void
  {
    $manager = $this->main();
    $connection = $manager->getConnection();
    foreach (['maintenance_operation_receipts', 'maintenance_occurrences', 'maintenance_plans', 'maintenance_engines'] as $table) {
      $connection->delete($table, ['organization_id' => self::ORG]);
    }
    $existing = $manager->find(OrganizationRecord::class, self::ORG);
    if (null !== $existing) {
      $manager->remove($existing);
      $manager->flush();
    }
    $now = new DateTimeImmutable('2026-10-06T00:00:00Z');
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Preventive plans test';
    $organization->slug = 'preventive-plans-test';
    $organization->ownerUserId = self::ADMIN;
    $organization->createdByUserId = self::ADMIN;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $organization->settings = ['regional' => ['timezone' => 'Europe/Paris']];
    $manager->persist($organization);
    foreach ([self::ADMIN => ['*'], self::READER => ['organization.maintenance.read']] as $userId => $permissions) {
      $role = new OrganizationRoleRecord();
      $role->id = '780e8401' . substr($userId, 8);
      $role->organization = $organization;
      $role->name = 'test-' . $userId;
      $role->permissions = $permissions;
      $role->createdAt = $now;
      $manager->persist($role);
      $member = new OrganizationMemberRecord();
      $member->id = '780e8402' . substr($userId, 8);
      $member->organization = $organization;
      $member->userId = $userId;
      $member->joinedAt = $now;
      $manager->persist($member);
      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $role;
      $assignment->assignedAt = $now;
      $manager->persist($assignment);
    }
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organization = $organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'operational';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $manager->persist($equipment);
    $manager->flush();
  }

  private function seedLegacySchedule(): void
  {
    $schedule = new MaintenanceScheduleRecord();
    $schedule->id = '780e8400-e29b-41d4-a716-446655440030';
    $schedule->organization = $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $schedule->equipmentId = self::EQUIPMENT;
    $schedule->equipmentType = 'fire_extinguisher';
    $schedule->intervalOverride = 'P6M';
    $schedule->lastInspectionClosedAt = new DateTimeImmutable('2026-09-01Z');
    $schedule->nextDueAt = new DateTimeImmutable('2027-03-01Z');
    $schedule->dueStatus = 'up_to_date';
    $schedule->createdAt = new DateTimeImmutable('2026-10-06Z');
    $schedule->updatedAt = new DateTimeImmutable('2026-10-06Z');
    $this->main()->persist($schedule);
    $this->main()->flush();
  }
}
