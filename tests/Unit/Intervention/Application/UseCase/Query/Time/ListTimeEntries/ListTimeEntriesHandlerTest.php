<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Time\ListTimeEntries;

use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Application\UseCase\Query\Time\ListTimeEntries\{ListTimeEntriesHandler, ListTimeEntriesQuery};
use Organization\Application\Contract\Workforce\OrganizationWorkforceMember;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class ListTimeEntriesHandlerTest
 *
 * Guards beneficiary filtering before bounded reads and complete counting.
 *
 * @category Test
 */
#[CoversClass(ListTimeEntriesHandler::class)]
final class ListTimeEntriesHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFiltersBothTheBoundedPageAndItsExactTotalToTheCaller(): void
  {
    $entries = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $entries->method('context')->willReturn(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []));
    $entries->expects(self::once())->method('list')->with('task', 'actor', 4, 20)->willReturn([]);
    $entries->expects(self::once())->method('count')->with('task', 'actor')->willReturn(65);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(false);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('actor', 'user', true)]);
    $policy = new InterventionTimeAccessPolicy($authorization, $workforce);
    $result = new ListTimeEntriesHandler($entries, $policy)(new ListTimeEntriesQuery('user', 'task', 4, 20, false));
    self::assertSame(65, $result->totalItems);
    self::assertSame(4, $result->page);
    self::assertSame(20, $result->itemsPerPage);
  }

  #[Test]
  public function testOwnOnlyKeepsAManagersPageAndTotalLimitedToTheCaller(): void
  {
    $entries = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $entries->method('context')->willReturn(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []));
    $entries->expects(self::once())->method('list')->with('task', 'actor', 2, 2)->willReturn([]);
    $entries->expects(self::once())->method('count')->with('task', 'actor')->willReturn(3);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('actor', 'user', true)]);
    $policy = new InterventionTimeAccessPolicy($authorization, $workforce);
    self::assertTrue($policy->canManage(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []), 'user'));
    $result = new ListTimeEntriesHandler($entries, $policy)(new ListTimeEntriesQuery('user', 'task', 2, 2, true));
    self::assertSame(3, $result->totalItems);
    self::assertSame(2, $result->page);
    self::assertSame(2, $result->itemsPerPage);
  }

  #[Test]
  public function testDefaultScopeRetainsTheManagersCompleteJournal(): void
  {
    $entries = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $entries->method('context')->willReturn(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []));
    $entries->expects(self::once())->method('list')->with('task', null, 1, 30)->willReturn([]);
    $entries->expects(self::once())->method('count')->with('task', null)->willReturn(6);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(true);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('actor', 'user', true)]);
    $policy = new InterventionTimeAccessPolicy($authorization, $workforce);
    $result = new ListTimeEntriesHandler($entries, $policy)(new ListTimeEntriesQuery('user', 'task'));
    self::assertSame(6, $result->totalItems);
  }
  // #endregion
}
