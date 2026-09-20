<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Adapter\Workforce\OrganizationWorkforceDirectoryAdapter;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Symfony\Component\Uid\Uuid;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

use function array_keys;
use function array_unique;
use function json_decode;
use function json_encode;
use function random_int;
use function sort;

use const JSON_THROW_ON_ERROR;

/**
 * WorkloadApiTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WorkloadApiTest extends WebTestCase
{
  private string $organizationId;

  private string $userId;

  private string $memberId;

  #[Test]
  public function testPickerProfilesKeepAvatarsAndRolesScopedToTheRequestedOrganization(): void
  {
    $this->seed(['organization.workload.read']);
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable();
    $otherOrganization = new OrganizationRecord();
    $otherOrganization->id = Uuid::v4()->toRfc4122();
    $otherOrganization->name = 'Other workload organization';
    $otherOrganization->slug = 'other-workload-' . $otherOrganization->id;
    $otherOrganization->ownerUserId = $this->userId;
    $otherOrganization->createdByUserId = $this->userId;
    $otherOrganization->status = 'active';
    $otherOrganization->isActive = true;
    $otherOrganization->createdAt = $now;
    $otherOrganization->updatedAt = $now;
    $em->persist($otherOrganization);
    $otherMember = new OrganizationMemberRecord();
    $otherMember->id = Uuid::v4()->toRfc4122();
    $otherMember->organization = $otherOrganization;
    $otherMember->userId = $this->userId;
    $otherMember->isActive = true;
    $otherMember->joinedAt = $now;
    $em->persist($otherMember);
    $otherRole = new OrganizationRoleRecord();
    $otherRole->id = Uuid::v4()->toRfc4122();
    $otherRole->organization = $otherOrganization;
    $otherRole->name = 'Other organization owner';
    $otherRole->permissions = ['*'];
    $otherRole->isSystem = false;
    $otherRole->createdAt = $now;
    $em->persist($otherRole);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $otherMember;
    $assignment->role = $otherRole;
    $assignment->assignedAt = $now;
    $em->persist($assignment);
    $em->flush();
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->willReturn(new GetUserResult(new UserView(
      $this->userId,
      'alex',
      'alex@example.test',
      'Alex',
      'Rivera',
      '/avatars/alex.webp',
      'active',
      true,
      null,
      $now,
      null,
      true,
    )));
    $directory = new OrganizationWorkforceDirectoryAdapter($em, $queries);
    self::assertSame([], $directory->profiles($this->organizationId, []));
    $profiles = $directory->profiles($this->organizationId, [$this->memberId, $otherMember->id]);
    self::assertSame([$this->memberId], array_keys($profiles));
    self::assertSame('Alex Rivera', $profiles[$this->memberId]->name);
    self::assertSame('/avatars/alex.webp', $profiles[$this->memberId]->avatarUrl);
    self::assertSame(['workload_test'], $profiles[$this->memberId]->roleNames);
    $body = $this->value($this->request('GET', '/workload?from=2026-09-14&to=2026-09-20'));
    self::assertResponseIsSuccessful();
    self::assertCount(1, $this->listValue($this->value($body, 'memberOptions')));
    self::assertSame(['workload_test'], $this->value($body, 'memberOptions', 0, 'roleNames'));
  }

  #[Test]
  public function testPaginationKeepsCompleteDailyCalculationsAndFiltersBeforeSlicing(): void
  {
    $this->seed(['*']);
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    for ($index = 0; $index < 2; ++$index) {
      $member = new OrganizationMemberRecord();
      $member->id = Uuid::v4()->toRfc4122();
      $member->organization = $em->getReference(OrganizationRecord::class, $this->organizationId);
      $member->userId = Uuid::v4()->toRfc4122();
      $member->isActive = true;
      $member->joinedAt = new DateTimeImmutable();
      $em->persist($member);
    }
    $em->flush();
    $this->seedTask(360, 'planned', $this->memberId);
    $this->seedTask(120, 'planned', $this->memberId);
    $this->seedTask(90, 'planned', null);
    $this->request('POST', '/workload/settings', ['effectiveOn' => '2020-01-01', 'minutes' => [420, 420, 420, 420, 420, 420, 420]]);
    self::assertResponseStatusCodeSame(201);
    $today = new DateTimeImmutable()->format('Y-m-d');
    $path = '/workload?from=' . $today . '&to=' . $today;
    $identifiers = [];
    for ($page = 1; $page <= 3; ++$page) {
      $body = $this->value($this->request('GET', $path . '&page=' . $page . '&pageSize=1'));
      self::assertResponseIsSuccessful();
      self::assertSame(3, $this->value($body, 'totalItems'));
      self::assertSame($page, $this->value($body, 'page'));
      self::assertSame(1, $this->value($body, 'pageSize'));
      self::assertCount(3, $this->listValue($this->value($body, 'memberOptions')));
      foreach ($this->listValue($this->value($body, 'memberOptions')) as $option) {
        self::assertNull($this->value($option, 'avatarUrl'));
        self::assertSame($this->value($option, 'id') === $this->memberId ? ['workload_test'] : [], $this->value($option, 'roleNames'));
      }
      self::assertCount(1, $this->listValue($this->value($body, 'projection', 'members')));
      self::assertCount(1, $this->listValue($this->value($body, 'projection', 'unassigned')));
      $identifiers[] = $this->stringValue($body, 'projection', 'members', 0, 'memberId');
    }
    self::assertCount(3, array_unique($identifiers));
    $sorted = $identifiers;
    sort($sorted);
    self::assertSame($sorted, $identifiers);
    $body = $this->value($this->request('GET', $path . '&overloaded=true&page=99&pageSize=1'));
    self::assertResponseIsSuccessful();
    self::assertSame(1, $this->value($body, 'totalItems'));
    self::assertSame(1, $this->value($body, 'page'));
    self::assertSame($this->memberId, $this->value($body, 'projection', 'members', 0, 'memberId'));
    self::assertSame(480, $this->value($body, 'projection', 'members', 0, 'days', 0, 'remainingMinutes'));
    self::assertSame(60, $this->value($body, 'projection', 'members', 0, 'days', 0, 'overloadMinutes'));
    self::assertCount(2, $this->listValue($this->value($body, 'projection', 'members', 0, 'days', 0, 'contributions')));
    $body = $this->value($this->request('GET', $path . '&member=' . $this->memberId . '&pageSize=1'));
    self::assertSame(1, $this->value($body, 'totalItems'));
    $body = $this->value($this->request('GET', $path . '&member=' . Uuid::v4() . '&page=9&pageSize=1'));
    self::assertSame(0, $this->value($body, 'totalItems'));
    self::assertSame(1, $this->value($body, 'page'));
    self::assertSame([], $this->value($body, 'projection', 'members'));
  }

  #[Test]
  public function testPaginationRejectsMalformedAndUnboundedValues(): void
  {
    $this->seed(['*']);
    foreach (['page=0', 'page=-1', 'page=1.5', 'page[]=1', 'pageSize=0', 'pageSize=101', 'pageSize=abc'] as $filter) {
      $this->request('GET', '/workload?from=2026-09-14&to=2026-09-20&' . $filter);
      self::assertResponseStatusCodeSame(400);
    }
  }

  #[Test]
  public function testCapacityIsUnknownUntilConfiguredAndWeeksAreEffectiveDated(): void
  {
    $this->seed(['*']);
    $client = $this->request('GET', '/workload?from=2026-09-14&to=2026-09-20');
    self::assertResponseIsSuccessful();
    $body = $this->value($client);
    self::assertNull($this->value($body, 'projection', 'members', 0, 'days', 0, 'capacityMinutes'));
    $this->request('POST', '/workload/settings', ['effectiveOn' => '2026-09-16', 'minutes' => [420, 420, 420, 420, 420, 0, 0]]);
    self::assertResponseStatusCodeSame(201);
    $client = $this->request('GET', '/workload?from=2026-09-14&to=2026-09-20');
    self::assertResponseIsSuccessful();
    $days = $this->value($this->value($client), 'projection', 'members', 0, 'days');
    self::assertNull($this->value($days, 0, 'capacityMinutes'));
    self::assertSame(420, $this->value($days, 2, 'capacityMinutes'));
    self::assertSame(0, $this->value($days, 6, 'capacityMinutes'));
  }

  #[Test]
  public function testExceptionsRejectOverlapAndCanBeCancelled(): void
  {
    $this->seed(['*']);
    $this->request('POST', '/workload/settings', ['effectiveOn' => '2026-01-01', 'minutes' => [420, 420, 420, 420, 420, 0, 0]]);
    self::assertResponseStatusCodeSame(201);
    $path = '/workload/members/' . $this->memberId . '/exceptions';
    $client = $this->request('POST', $path, ['startsOn' => '2026-09-16', 'endsOn' => '2026-09-17', 'minutes' => 0]);
    self::assertResponseStatusCodeSame(201);
    $id = $this->value($client, 'id');
    self::assertIsString($id);
    $this->request('POST', $path, ['startsOn' => '2026-09-17', 'endsOn' => '2026-09-18', 'minutes' => 60]);
    self::assertResponseStatusCodeSame(400);
    $this->request('DELETE', $path . '/' . $id);
    self::assertResponseStatusCodeSame(204);
    $this->request('POST', $path, ['startsOn' => '2026-09-17', 'endsOn' => '2026-09-18', 'minutes' => 60]);
    self::assertResponseStatusCodeSame(201);
  }

  #[Test]
  public function testAgentsCanReadTheirOwnWorkloadButCannotManageCapacityOrReadOtherMembers(): void
  {
    $this->seed([]);
    $client = $this->request('GET', '/workload?from=2026-09-14&to=2026-09-20');
    self::assertResponseIsSuccessful();
    $options = $this->value($this->value($client), 'memberOptions');
    self::assertCount(1, $this->listValue($options));
    self::assertSame($this->memberId, $this->value($options, 0, 'id'));
    self::assertSame(['workload_test'], $this->value($options, 0, 'roleNames'));
    $this->request('POST', '/workload/settings', ['effectiveOn' => '2026-01-01', 'minutes' => [0, 0, 0, 0, 0, 0, 0]]);
    self::assertResponseStatusCodeSame(403);
    $this->request('GET', '/workload?from=2026-09-14&to=2026-09-20&member=' . Uuid::v4());
    self::assertResponseStatusCodeSame(403);
    $this->userId = Uuid::v4()->toRfc4122();
    $this->request('GET', '/workload?from=2026-09-14&to=2026-09-20');
    self::assertResponseStatusCodeSame(404);
  }

  #[Test]
  public function testOverloadRequiresCurrentConfirmationAndNeverPartiallyApplies(): void
  {
    $this->seed(['*']);
    $today = new DateTimeImmutable()->format('Y-m-d');
    $this->request('POST', '/workload/settings', ['effectiveOn' => '2020-01-01', 'minutes' => [420, 420, 420, 420, 420, 420, 420]]);
    self::assertResponseStatusCodeSame(201);
    $this->seedTask(360, 'planned', $this->memberId);
    $task = $this->seedTask(120, 'planned', null);
    $path = '/api/intervention-work-items/' . $task;
    $payload = ['assignee' => '/api/organizations/' . $this->organizationId . '/members/' . $this->memberId];
    $client = $this->requestApi('PATCH', $path, $payload, 1);
    self::assertResponseStatusCodeSame(409);
    $assessment = $this->value($this->value($client), 'assessment');
    self::assertSame(0, $this->value($assessment, 'increases', 0, 'beforeMinutes'));
    self::assertSame(60, $this->value($assessment, 'increases', 0, 'afterMinutes'));
    $oldToken = $this->value($assessment, 'confirmationToken');
    $this->request('POST', '/workload/settings', ['effectiveOn' => $today, 'minutes' => [400, 400, 400, 400, 400, 400, 400]]);
    self::assertResponseStatusCodeSame(201);
    $client = $this->requestApi('PATCH', $path, [...$payload, 'workloadConfirmationToken' => $oldToken], 1);
    self::assertResponseStatusCodeSame(409);
    $assessment = $this->value($this->value($client), 'assessment');
    self::assertSame(80, $this->value($assessment, 'increases', 0, 'afterMinutes'));
    self::assertNotSame($oldToken, $this->value($assessment, 'confirmationToken'));
    $client = $this->requestApi('PATCH', $path, [...$payload, 'workloadConfirmationToken' => $this->value($assessment, 'confirmationToken')], 1);
    self::assertResponseIsSuccessful();
    self::assertSame(2, $this->value($this->value($client), 'revision'));
  }

  #[Test]
  public function testTimeAfterPublicationIsIndependentVersionedAndIdempotent(): void
  {
    $this->seed(['*']);
    $task = $this->seedTask(180, 'published', $this->memberId);
    $path = '/api/intervention-work-items/' . $task . '/time-entries';
    $id = Uuid::v4()->toRfc4122();
    $payload = ['id' => $id, 'workedOn' => new DateTimeImmutable()->format('Y-m-d'), 'minutes' => 120, 'note' => 'Field work'];
    $this->requestApi('POST', $path, $payload);
    self::assertResponseStatusCodeSame(201);
    $client = $this->requestApi('POST', $path, $payload);
    self::assertResponseStatusCodeSame(201);
    $entry = $this->value($this->value($client), 'entry');
    self::assertSame(1, $this->value($entry, 'revision'));
    self::assertCount(1, $this->listValue($this->value($entry, 'versions')));
    $client = $this->requestApi('GET', '/api/intervention-work-items/' . $task);
    self::assertResponseIsSuccessful();
    $item = $this->value($client);
    self::assertSame(180, $this->value($item, 'remainingMinutes'));
    self::assertSame(1, $this->value($item, 'revision'));
    $client = $this->requestApi('GET', $this->stringValue($item, 'intervention'));
    self::assertResponseIsSuccessful();
    self::assertSame(1, $this->value($this->value($client), 'revision'));
    $correction = ['workedOn' => $payload['workedOn'], 'minutes' => 90, 'note' => 'Corrected'];
    $this->requestApi('PATCH', $path . '/' . $id, $correction, 1);
    self::assertResponseIsSuccessful();
    $client = $this->requestApi('PATCH', $path . '/' . $id, $correction, 1);
    self::assertResponseIsSuccessful();
    $entry = $this->value($this->value($client), 'entry');
    self::assertSame(2, $this->value($entry, 'revision'));
    self::assertCount(2, $this->listValue($this->value($entry, 'versions')));
    $this->requestApi('PATCH', $path . '/' . $id, [...$correction, 'minutes' => 60], 1);
    self::assertResponseStatusCodeSame(412);
    $this->requestApi('DELETE', $path . '/' . $id, null, 2);
    self::assertResponseStatusCodeSame(204);
    $client = $this->requestApi('GET', $path);
    self::assertResponseIsSuccessful();
    $entry = $this->value($this->value($client), 'entries', 0);
    self::assertTrue($this->value($entry, 'cancelled'));
    self::assertCount(3, $this->listValue($this->value($entry, 'versions')));
  }

  #[Test]
  public function testTimeWriteRequiresDedicatedPermission(): void
  {
    $this->seed(['organization.interventions.read']);
    $task = $this->seedTask(60, 'published', $this->memberId);
    $this->requestApi('POST', '/api/intervention-work-items/' . $task . '/time-entries', ['workedOn' => '2026-01-01', 'minutes' => 60]);
    self::assertResponseStatusCodeSame(403);
  }

  #[Test]
  public function testAssessmentExcludesTheOldContributionAndItsTokenAcceptsTheExactWrite(): void
  {
    $this->seed(['*']);
    $today = new DateTimeImmutable()->format('Y-m-d');
    $this->request('POST', '/workload/settings', ['effectiveOn' => '2020-01-01', 'minutes' => [420, 420, 420, 420, 420, 420, 420]]);
    $this->seedTask(360, 'planned', $this->memberId);
    $task = $this->seedTask(120, 'planned', null);
    $client = $this->request('POST', '/workload/assessments', ['changes' => [[
      'taskId' => $task, 'memberId' => $this->memberId, 'remainingMinutes' => 120, 'startsOn' => $today, 'endsOn' => $today,
    ]]]);
    self::assertResponseIsSuccessful();
    $assessment = $this->value($this->value($client), 'assessment');
    self::assertTrue($this->value($assessment, 'confirmationRequired'));
    self::assertSame(60, $this->value($assessment, 'increases', 0, 'afterMinutes'));
    $this->requestApi('PATCH', '/api/intervention-work-items/' . $task, [
      'assignee' => '/api/organizations/' . $this->organizationId . '/members/' . $this->memberId,
      'workloadConfirmationToken' => $this->value($assessment, 'confirmationToken'),
    ], 1);
    self::assertResponseIsSuccessful();
    $client = $this->request('POST', '/workload/assessments', ['changes' => [[
      'taskId' => $task, 'memberId' => $this->memberId, 'remainingMinutes' => 120, 'startsOn' => $today, 'endsOn' => $today,
    ]]]);
    self::assertResponseIsSuccessful();
    self::assertFalse($this->value($this->value($client), 'assessment', 'confirmationRequired'));
  }

  #[Test]
  public function testFormerAssigneeKeepsTimeRightsAndHistoryPreventsDeletion(): void
  {
    $this->seed(['organization.interventions.read', 'organization.interventions.plan', 'organization.interventions.time.write']);
    $task = $this->seedTask(60, 'draft', $this->memberId);
    $path = '/api/intervention-work-items/' . $task;
    $this->requestApi('PATCH', $path, ['assignee' => null], 1);
    self::assertResponseIsSuccessful();
    $client = $this->requestApi('GET', $path);
    self::assertResponseIsSuccessful();
    $body = $this->value($client);
    self::assertTrue($this->value($body, 'allowedActions', 'canLogTime'));
    $this->requestApi('POST', $path . '/time-entries', ['workedOn' => '2026-01-01', 'minutes' => 60]);
    self::assertResponseStatusCodeSame(201);
    $this->requestApi('DELETE', $path, null, 2);
    self::assertResponseStatusCodeSame(409);
    $client = $this->requestApi('GET', $this->stringValue($body, 'intervention'));
    $revision = $this->value($this->value($client), 'revision');
    self::assertIsInt($revision);
    $this->requestApi('DELETE', $this->stringValue($body, 'intervention'), null, $revision);
    self::assertResponseStatusCodeSame(409);
  }

  /**
   * Traverses the wire response with runtime shape assertions.
   */
  private function value(mixed $source, string|int ...$path): mixed
  {
    if ($source instanceof KernelBrowser) {
      $content = $source->getResponse()->getContent();
      self::assertIsString($content);
      $source = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }
    foreach ($path as $key) {
      self::assertIsArray($source);
      self::assertArrayHasKey($key, $source);
      $source = $source[$key];
    }

    return $source;
  }

  private function stringValue(mixed $source, string|int ...$path): string
  {
    $value = $this->value($source, ...$path);
    self::assertIsString($value);

    return $value;
  }

  /**
   * @return array<array-key, mixed>
   */
  private function listValue(mixed $value): array
  {
    self::assertIsArray($value);

    return $value;
  }

  private function seedTask(int $minutes, string $status, ?string $assignee): string
  {
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable();
    $parent = new \Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord();
    $parent->id = Uuid::v4()->toRfc4122();
    $parent->organization = $em->getReference(OrganizationRecord::class, $this->organizationId);
    $parent->name = 'Workload test intervention';
    $parent->number = random_int(100000, 99999999);
    $parent->type = 'inventory';
    $parent->status = $status;
    $parent->priority = 'normal';
    $parent->plannedStartAt = $now;
    $parent->dueAt = $now;
    $parent->createdAt = $now;
    $parent->updatedAt = $now;
    $em->persist($parent);
    $task = new \Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionWorkItemRecord();
    $task->id = Uuid::v4()->toRfc4122();
    $task->intervention = $parent;
    $task->action = 'inventory';
    $task->estimatedMinutes = $minutes;
    $task->remainingMinutes = $minutes;
    $task->assigneeId = $assignee;
    $task->createdAt = $now;
    $task->updatedAt = $now;
    $em->persist($task);
    $em->flush();

    return $task->id;
  }

  /**
   * @param list<string> $permissions
   */
  private function seed(array $permissions): void
  {
    static::ensureKernelShutdown();
    static::createClient();
    $this->organizationId = Uuid::v4()->toRfc4122();
    $this->userId = Uuid::v4()->toRfc4122();
    $this->memberId = Uuid::v4()->toRfc4122();
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = $this->organizationId;
    $organization->name = 'Workload test';
    $organization->slug = 'workload-test-' . $this->organizationId;
    $organization->ownerUserId = Uuid::v4()->toRfc4122();
    $organization->createdByUserId = $organization->ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $em->persist($organization);
    $member = new OrganizationMemberRecord();
    $member->id = $this->memberId;
    $member->organization = $organization;
    $member->userId = $this->userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $em->persist($member);
    $role = new OrganizationRoleRecord();
    $role->id = Uuid::v4()->toRfc4122();
    $role->organization = $organization;
    $role->name = 'workload_test';
    $role->permissions = $permissions;
    $role->isSystem = false;
    $role->createdAt = $now;
    $em->persist($role);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $em->persist($assignment);
    $em->flush();
  }

  /**
   * @param array<string, mixed>|null $payload
   */
  private function request(string $method, string $suffix, ?array $payload = null): KernelBrowser
  {
    return $this->requestApi($method, '/api/organizations/' . $this->organizationId . $suffix, $payload);
  }

  /**
   * @param array<string, mixed>|null $payload
   */
  private function requestApi(string $method, string $uri, ?array $payload = null, ?int $revision = null): KernelBrowser
  {
    static::ensureKernelShutdown();
    $client = static::createClient();
    $client->loginUser(new SecurityUser(id: $this->userId, email: 'workload@example.com', password: 'hashed', roles: ['ROLE_USER']), 'api');
    $server = ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    if (null !== $revision) {
      $server['HTTP_IF_MATCH'] = '"revision-' . $revision . '"';
    }
    $client->request(
      $method,
      $uri,
      server: $server,
      content: null === $payload ? null : json_encode($payload, JSON_THROW_ON_ERROR),
    );

    return $client;
  }
}
