<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionTimeEntryRecord, InterventionWorkItemRecord};
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\WriteMaintenanceCostCommand;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function json_decode;
use function json_encode;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

/** Private cost preparation and append-only expenses retain permissions, scope, revisions and exact amounts. */
final class MaintenanceCostApiTest extends WebTestCase
{
  private const string ORG = '750e8400-e29b-41d4-a716-448040000001';

  private const string OWNER = '750e8400-e29b-41d4-a716-448040000002';

  private const string USER = '750e8400-e29b-41d4-a716-448040000003';

  private const string MEMBER = '750e8400-e29b-41d4-a716-448040000004';

  private const string READER = '750e8400-e29b-41d4-a716-448040000007';

  private const string WORK = '750e8400-e29b-41d4-a716-448040000020';

  private const string TASK = '750e8400-e29b-41d4-a716-448040000021';

  #[Test]
  public function draftExpenseWithoutTimeRetainsItsInterventionAndReportTotal(): void
  {
    $client = $this->client();
    $this->ownerBearerSession($client);
    $work = $this->main()->find(InterventionRecord::class, self::WORK);
    self::assertInstanceOf(InterventionRecord::class, $work);
    $work->createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
    $this->main()->flush();
    $expenseId = $this->seedExpense();
    $client->request('DELETE', '/api/interventions/' . self::WORK, server: ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $cost = $this->request($client, 'GET');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertIsArray($cost['current']);
    self::assertSame('50.000001', $cost['current']['total']);
    self::assertIsArray($cost['current']['items']);
    self::assertCount(1, $cost['current']['items']);
    self::assertIsArray($cost['current']['items'][0]);
    self::assertSame($expenseId, $cost['current']['items'][0]['sourceId']);
    $client->request('GET', '/api/organizations/' . self::ORG . '/maintenance-cost/reports?from=2026-01-01&to=2026-01-31', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $report = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($report);
    self::assertSame(1, $report['interventionCount']);
    self::assertIsArray($report['current']);
    self::assertSame('50.000001', $report['current']['total']);
  }

  #[Test]
  public function taskExpenseRetainsItsPreparedTask(): void
  {
    $client = $this->client();
    $this->ownerBearerSession($client);
    $expenseId = $this->seedExpense();
    $client->request('DELETE', '/api/intervention-work-items/' . self::TASK, server: ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $cost = $this->request($client, 'GET');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertIsArray($cost['current']);
    self::assertIsArray($cost['current']['items']);
    self::assertIsArray($cost['current']['items'][0]);
    self::assertSame($expenseId, $cost['current']['items'][0]['sourceId']);
    self::assertSame(self::TASK, $cost['current']['items'][0]['workItemId']);
  }

  #[Test]
  public function preparedFinancialResourcesRetainTheirTaskAndParent(): void
  {
    $client = $this->client();
    $this->ownerBearerSession($client);
    $this->request($client, 'PATCH', '/planning', ['resources' => [['workItemId' => self::TASK, 'kind' => 'external', 'description' => 'Prepared specialist visit', 'amount' => '30']]], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    foreach (['/api/intervention-work-items/' . self::TASK, '/api/interventions/' . self::WORK] as $path) {
      $client->request('DELETE', $path, server: ['HTTP_IF_MATCH' => '"revision-1"']);
      self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }
    $planning = $this->costStore()->planning(self::ORG, self::WORK);
    self::assertSame(1, $planning->revision);
    self::assertSame(self::TASK, $planning->resources[0]['workItemId']);
    self::assertSame('30.000000', $planning->resources[0]['amount']);
  }

  #[Test]
  public function anEmptyInterventionHasAKnownZeroCost(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'GET');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('EUR', $body['currency']);
    self::assertSame(0, $body['planningRevision']);
    self::assertNull($body['frozen']);
    self::assertIsArray($body['current']);
    self::assertSame('0.000000', $body['current']['total']);
    self::assertSame('0.000000', $body['current']['knownTotal']);
    self::assertTrue($body['current']['complete']);
  }

  #[Test]
  public function recordedTimeWithoutARateRemainsAnIncompleteCost(): void
  {
    $client = $this->client();
    $this->seedTime();
    $body = $this->request($client, 'GET');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['current']);
    self::assertNull($body['current']['total']);
    self::assertSame('0.000000', $body['current']['knownTotal']);
    self::assertFalse($body['current']['complete']);
    self::assertIsArray($body['current']['items']);
    self::assertCount(1, $body['current']['items']);
    self::assertIsArray($body['current']['items'][0]);
    self::assertNull($body['current']['items'][0]['amount']);
    self::assertNull($body['current']['items'][0]['hourlyAmount']);
  }

  #[Test]
  public function financialPlanningUsesItsOwnRevisionAndExactResourceAmounts(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'PATCH', '/planning', [
      'plannedBudget' => '100.000001', 'estimatedMinutes' => 30,
      'resources' => [
        ['workItemId' => self::TASK, 'kind' => 'material', 'description' => 'Valve seals', 'quantity' => '2', 'unitCost' => '10.123456'],
        ['workItemId' => self::TASK, 'kind' => 'time', 'description' => 'Repair labor', 'estimatedMinutes' => 30, 'unitCost' => '60'],
      ],
    ], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $body['planningRevision']);
    self::assertSame('100.000001', $body['plannedBudget']);
    self::assertSame(30, $body['estimatedMinutes']);
    self::assertIsArray($body['resources']);
    self::assertCount(2, $body['resources']);
    self::assertIsArray($body['resources'][0]);
    self::assertIsArray($body['resources'][1]);
    self::assertSame('20.246912', $body['resources'][0]['amount']);
    self::assertSame('30.000000', $body['resources'][1]['amount']);
    self::assertIsArray($body['current']);
    self::assertSame('0.000000', $body['current']['total']);
  }

  #[Test]
  public function timePlanningRecomputesItsAmountFromMinutesAndHourlyRate(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'PATCH', '/planning', ['resources' => [['workItemId' => self::TASK, 'kind' => 'time', 'description' => 'Repair labor', 'quantity' => null, 'unitCost' => '50', 'estimatedMinutes' => 60, 'amount' => '100']]], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['resources']);
    self::assertIsArray($body['resources'][0]);
    self::assertSame('50.000000', $body['resources'][0]['amount']);
  }

  /**
   * @return iterable<string,array{array<string,mixed>}>
   */
  public static function incompatibleResourceFields(): iterable
  {
    yield 'material quantity retained after selecting time' => [['kind' => 'time', 'quantity' => '2', 'unitCost' => '50', 'estimatedMinutes' => 60]];
    yield 'work minutes retained after selecting material' => [['kind' => 'material', 'quantity' => '2', 'unitCost' => '50', 'estimatedMinutes' => 60]];
    yield 'material quantity retained after selecting external expense' => [['kind' => 'external', 'quantity' => '2', 'amount' => '50']];
    yield 'unit cost retained after selecting external expense' => [['kind' => 'external', 'unitCost' => '50', 'amount' => '50']];
    yield 'work minutes retained after selecting external expense' => [['kind' => 'external', 'estimatedMinutes' => 60, 'amount' => '50']];
  }

  /**
   * @param array<string,mixed> $resource
   */
  #[Test]
  #[DataProvider('incompatibleResourceFields')]
  public function resourceKindRejectsIncompatibleFieldsWithoutSavingABudget(array $resource): void
  {
    $client = $this->client();
    $this->request($client, 'PATCH', '/planning', ['plannedBudget' => '100', 'resources' => [$resource + ['workItemId' => self::TASK, 'description' => 'Prepared repair resource']]], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $planning = $this->costStore()->planning(self::ORG, self::WORK);
    self::assertSame(0, $planning->revision);
    self::assertNull($planning->plannedBudget);
    self::assertSame([], $planning->resources);
  }

  #[Test]
  public function externalPlanningRetainsOnlyItsExplicitAmount(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'PATCH', '/planning', ['resources' => [['workItemId' => self::TASK, 'kind' => 'external', 'description' => 'Specialist visit', 'quantity' => null, 'unitCost' => null, 'estimatedMinutes' => null, 'amount' => '50.123456']]], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['resources']);
    self::assertIsArray($body['resources'][0]);
    self::assertSame('50.123456', $body['resources'][0]['amount']);
  }

  /**
   * @return iterable<string,array{array<string,mixed>}>
   */
  public static function incompleteResourceSources(): iterable
  {
    yield 'unknown hourly rate' => [['kind' => 'time', 'unitCost' => null, 'estimatedMinutes' => 60]];
    yield 'unknown work duration' => [['kind' => 'time', 'unitCost' => '50', 'estimatedMinutes' => null]];
    yield 'unknown part unit cost' => [['kind' => 'material', 'unitCost' => null, 'quantity' => '2']];
    yield 'unknown material quantity' => [['kind' => 'material', 'unitCost' => '50', 'quantity' => null]];
  }

  /**
   * @param array<string,mixed> $resource
   */
  #[Test]
  #[DataProvider('incompleteResourceSources')]
  public function incompletePlanningSourcesDoNotRetainAnEarlierCalculatedAmount(array $resource): void
  {
    $client = $this->client();
    $body = $this->request($client, 'PATCH', '/planning', ['resources' => [$resource + ['workItemId' => self::TASK, 'description' => 'Repair resource', 'amount' => '100']]], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['resources']);
    self::assertIsArray($body['resources'][0]);
    self::assertNull($body['resources'][0]['amount']);
  }

  #[Test]
  public function planningRequiresItsExpectedRevision(): void
  {
    $client = $this->client();
    $this->request($client, 'PATCH', '/planning', ['plannedBudget' => '100']);
    self::assertSame(428, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function stalePlanningCannotOverwriteTheCurrentRevision(): void
  {
    $client = $this->client();
    $this->request($client, 'PATCH', '/planning', ['plannedBudget' => '100'], ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(412, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function operationalMemberCannotReadFinancialAmounts(): void
  {
    $client = $this->client(self::USER);
    $this->request($client, 'GET');
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function financialReaderCannotWritePlanning(): void
  {
    $client = $this->client(self::READER);
    $this->request($client, 'PATCH', '/planning', ['plannedBudget' => '100'], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function managementWithoutReadCannotObtainThePrivateProjectionByWritingAnExpense(): void
  {
    $client = $this->client(self::READER, ['organization.maintenance_cost.manage']);
    $this->seedExpense();
    $input = $this->expenseInput();
    $input['clientId'] = 'unreadable-expense-2';
    $body = $this->request($client, 'POST', '/expenses', $input);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertArrayNotHasKey('current', $body);
    self::assertArrayNotHasKey('frozen', $body);
    self::assertStringNotContainsString('50.000001', (string) $client->getResponse()->getContent());
    self::assertCount(1, $this->costStore()->expenses(self::ORG, self::WORK));
  }

  #[Test]
  public function managementWithoutReadCannotObtainCostsByPreparingABudget(): void
  {
    $client = $this->client(self::READER, ['organization.maintenance_cost.manage']);
    $body = $this->request($client, 'PATCH', '/planning', ['plannedBudget' => '100'], ['HTTP_IF_MATCH' => '"revision-0"']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertArrayNotHasKey('current', $body);
    self::assertSame(0, $this->costStore()->planning(self::ORG, self::WORK)->revision);
  }

  #[Test]
  public function readOnlyFinanceReceivesANonEditablePlanningCapability(): void
  {
    $client = $this->client(self::READER);
    $body = $this->request($client, 'GET');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertFalse($body['planningEditable']);
  }

  #[Test]
  public function outsiderCannotDiscoverInterventionFinance(): void
  {
    $client = $this->client('750e8400-e29b-41d4-a716-448040000099');
    $this->request($client, 'GET');
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function authorizedReaderCannotCrossTheInterventionOrganizationBoundary(): void
  {
    $client = $this->client();
    $foreign = new OrganizationRecord();
    $foreign->id = '750e8400-e29b-41d4-a716-448040000091';
    $foreign->name = 'Other finance scope';
    $foreign->slug = 'other-finance-scope';
    $foreign->ownerUserId = '750e8400-e29b-41d4-a716-448040000099';
    $foreign->createdByUserId = $foreign->ownerUserId;
    $foreign->status = 'active';
    $foreign->isActive = true;
    $foreign->createdAt = new DateTimeImmutable();
    $foreign->updatedAt = $foreign->createdAt;
    $this->main()->persist($foreign);
    $work = $this->seedWork($foreign, '750e8400-e29b-41d4-a716-448040000092');
    $this->request($client, 'GET', workId: $work->id);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function appendsAnExactExpenseAndFreezesItsCurrency(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'POST', '/expenses', $this->expenseInput());
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['current']);
    self::assertSame('50.000001', $body['current']['total']);
    self::assertSame('50.000001', $body['current']['knownTotal']);
    self::assertTrue($body['current']['complete']);
    self::assertTrue($this->main()->getConnection()->fetchOne('SELECT locked FROM maintenance_cost_currency_settings WHERE organization_id = :org', ['org' => self::ORG]));
    self::assertCount(1, $this->costStore()->expenses(self::ORG, self::WORK));
  }

  #[Test]
  public function exactReplayRetainsOnePhysicalExpenseFact(): void
  {
    $client = $this->client();
    $original = $this->seedExpense();
    $body = $this->request($client, 'POST', '/expenses', $this->expenseInput());
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['current']);
    self::assertSame('50.000001', $body['current']['total']);
    self::assertCount(1, $this->costStore()->expenses(self::ORG, self::WORK));
    self::assertSame($original, $this->costStore()->expenseByClientId(self::ORG, 'external-expense-1')?->id);
  }

  #[Test]
  public function conflictingReplayCannotOverwriteAnExpense(): void
  {
    $client = $this->client();
    $this->seedExpense();
    $input = $this->expenseInput();
    $input['amount'] = '51.000001';
    $this->request($client, 'POST', '/expenses', $input);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('50.000001', $this->costStore()->expenseByClientId(self::ORG, 'external-expense-1')?->amount);
  }

  #[Test]
  public function uuidExpenseReplayNormalizesItsCaseWithoutDuplicatingTheFact(): void
  {
    $client = $this->client();
    $input = $this->expenseInput();
    $input['clientId'] = 'abc18400-e29b-41d4-a716-448040000041';
    /** @var CommandBusPort $commands */
    $commands = self::getContainer()->get(CommandBusPort::class);
    $commands->dispatch(new WriteMaintenanceCostCommand(self::OWNER, self::ORG, self::WORK, 'expense', $input));
    $input['clientId'] = strtoupper($input['clientId']);
    $this->request($client, 'POST', '/expenses', $input);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertCount(1, $this->costStore()->expenses(self::ORG, self::WORK));
    self::assertSame('abc18400-e29b-41d4-a716-448040000041', $this->costStore()->expenses(self::ORG, self::WORK)[0]->clientId);
  }

  #[Test]
  public function negativeExpenseRequiresTheOriginalFact(): void
  {
    $client = $this->client();
    $input = $this->expenseInput();
    $input['amount'] = '-10';
    $this->request($client, 'POST', '/expenses', $input);
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertCount(0, $this->costStore()->expenses(self::ORG, self::WORK));
  }

  #[Test]
  public function motivatedCorrectionRetainsItsOriginalExpense(): void
  {
    $client = $this->client();
    $original = $this->seedExpense();
    $input = $this->expenseInput();
    $input['clientId'] = 'external-expense-adjustment-1';
    $input['amount'] = '-10';
    $input['description'] = 'Supplier credit for returned unused part';
    $input['adjustmentOf'] = $original;
    $body = $this->request($client, 'POST', '/expenses', $input);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['current']);
    self::assertSame('40.000001', $body['current']['total']);
    self::assertSame($original, $this->costStore()->expenseByClientId(self::ORG, 'external-expense-adjustment-1')?->adjustmentOf);
    self::assertCount(2, $this->costStore()->expenses(self::ORG, self::WORK));
  }

  #[Test]
  public function floatingPointAmountIsRejected(): void
  {
    $client = $this->client();
    $input = $this->expenseInput();
    $input['amount'] = 50.1;
    $this->request($client, 'POST', '/expenses', $input);
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertCount(0, $this->costStore()->expenses(self::ORG, self::WORK));
  }

  #[Test]
  public function ordinaryInterventionReadDoesNotEmbedPrivateCosts(): void
  {
    $client = $this->client();
    $this->seedExpense();
    $client->request('GET', '/api/interventions/' . self::WORK, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    /** @var array<string,mixed> $body */
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    foreach (['currency', 'costs', 'plannedBudget', 'current', 'frozen', 'maintenanceCost'] as $field) {
      self::assertArrayNotHasKey($field, $body);
    }
  }

  /**
   * @param list<string>|null $readerPermissions
   */
  private function client(string $actor = self::OWNER, ?array $readerPermissions = null): KernelBrowser
  {
    $client = static::createClient();
    $em = $this->main();
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Maintenance Costs API';
    $organization->slug = 'maintenance-costs-api';
    $organization->ownerUserId = self::OWNER;
    $organization->createdByUserId = self::OWNER;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $em->persist($organization);
    foreach ([self::OWNER => '750e8400-e29b-41d4-a716-448040000005', self::USER => self::MEMBER, self::READER => '750e8400-e29b-41d4-a716-448040000008'] as $userId => $memberId) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $organization;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $em->persist($member);
      if (self::OWNER === $userId || self::READER === $userId) {
        $role = new OrganizationRoleRecord();
        $role->id = self::OWNER === $userId ? '750e8400-e29b-41d4-a716-448040000006' : '750e8400-e29b-41d4-a716-448040000009';
        $role->organization = $organization;
        $role->name = self::OWNER === $userId ? 'cost_owner' : 'cost_reader';
        $role->permissions = self::OWNER === $userId ? ['*'] : ($readerPermissions ?? ['organization.maintenance_cost.read']);
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
    $this->seedWork($organization);
    $em->flush();
    $client->loginUser(new SecurityUser($actor, $actor . '@example.com', 'password', ['ROLE_USER']), 'api');

    return $client;
  }

  private function seedWork(OrganizationRecord $organization, string $id = self::WORK): InterventionRecord
  {
    $now = new DateTimeImmutable();
    $work = new InterventionRecord();
    $work->id = $id;
    $work->organization = $organization;
    $work->name = 'Fire park repair';
    $work->number = 1;
    $work->type = 'corrective_maintenance';
    $work->status = 'draft';
    $work->createdAt = $now;
    $work->updatedAt = $now;
    $this->main()->persist($work);
    if (self::WORK === $id) {
      $task = new InterventionWorkItemRecord();
      $task->id = self::TASK;
      $task->intervention = $work;
      $task->action = 'repair';
      $task->status = 'planned';
      $task->source = 'planned';
      $task->createdAt = $now;
      $task->updatedAt = $now;
      $this->main()->persist($task);
    }
    $this->main()->flush();

    return $work;
  }

  private function seedTime(): void
  {
    $entry = new InterventionTimeEntryRecord();
    $entry->id = '750e8400-e29b-41d4-a716-448040000030';
    $entry->workItem = $this->main()->getReference(InterventionWorkItemRecord::class, self::TASK);
    $entry->organizationId = self::ORG;
    $entry->memberId = self::MEMBER;
    $entry->workedOn = '2026-01-01';
    $entry->minutes = 30;
    $entry->createdBy = self::OWNER;
    $entry->updatedBy = self::OWNER;
    $entry->createdAt = new DateTimeImmutable('2026-01-01T12:00:00Z');
    $entry->updatedAt = $entry->createdAt;
    $this->main()->persist($entry);
    $this->main()->flush();
  }

  private function seedExpense(): string
  {
    /** @var CommandBusPort $commands */
    $commands = self::getContainer()->get(CommandBusPort::class);
    $commands->dispatch(new WriteMaintenanceCostCommand(self::OWNER, self::ORG, self::WORK, 'expense', $this->expenseInput()));
    $expense = $this->costStore()->expenseByClientId(self::ORG, 'external-expense-1');
    self::assertNotNull($expense);

    return $expense->id;
  }

  private function costStore(): MaintenanceCostStorePort
  {
    /** @var MaintenanceCostStorePort $store */
    $store = self::getContainer()->get(MaintenanceCostStorePort::class);

    return $store;
  }

  private function ownerBearerSession(KernelBrowser $client): void
  {
    $client->disableReboot();
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (UserId $id) => UserTestFactory::createActive((string) $id, (string) $id . '@example.com'));
    self::getContainer()->set(UserRepositoryPort::class, $users);
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(self::getContainer(), self::OWNER, self::OWNER . '@example.com'));
  }

  private function main(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $em */
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');

    return $em;
  }

  /**
   * @return array<string,mixed>
   */
  private function expenseInput(): array
  {
    return ['clientId' => 'external-expense-1', 'amount' => '50.000001', 'description' => 'External specialist repair', 'incurredAt' => '2026-01-01T12:00:00+00:00', 'workItemId' => self::TASK];
  }

  /**
   * @param array<string,mixed>|null $body
   * @param array<string,string> $headers
   *
   * @return array<string,mixed>
   */
  private function request(KernelBrowser $client, string $method, string $suffix = '', ?array $body = null, array $headers = [], string $workId = self::WORK): array
  {
    $client->request($method, '/api/organizations/' . self::ORG . '/interventions/' . $workId . '/costs' . $suffix, server: $headers + [
      'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
    ], content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
    /** @var array<string,mixed> $decoded */
    $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
  }
}
