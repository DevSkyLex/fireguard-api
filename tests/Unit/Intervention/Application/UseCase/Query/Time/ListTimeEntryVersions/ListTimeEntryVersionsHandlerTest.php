<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Time\ListTimeEntryVersions;

use Intervention\Application\Contract\Time\{TimeEntryTaskContext, TimeEntryVersionView, TimeEntryView};
use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Application\UseCase\Query\Time\ListTimeEntryVersions\{ListTimeEntryVersionsHandler, ListTimeEntryVersionsQuery};
use Intervention\Domain\Exception\InterventionNotFoundException;
use InvalidArgumentException;
use Organization\Application\Contract\Workforce\OrganizationWorkforceMember;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class ListTimeEntryVersionsHandlerTest
 *
 * Covers query bounds, stable continuation and beneficiary isolation.
 *
 * @category Test
 */
#[CoversClass(ListTimeEntryVersionsHandler::class)]
final class ListTimeEntryVersionsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testReadsOnlyOneBoundedCursorWindowAndKeepsTheCompleteCount(): void
  {
    $entries = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $entries->method('context')->willReturn(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []));
    $entries->method('find')->willReturn(new TimeEntryView('entry', 'task', 'actor', '2026-01-01', 60, null, 500, false, 'actor', 'actor', 'now', 'now'));
    $entries->expects(self::once())->method('versions')->with('entry', 401, 3)->willReturn([
      new TimeEntryVersionView(400, '2026-01-01', 60, null, false, 'actor', 'now'),
      new TimeEntryVersionView(399, '2026-01-01', 60, null, false, 'actor', 'now'),
      new TimeEntryVersionView(398, '2026-01-01', 60, null, false, 'actor', 'now'),
    ]);
    $entries->expects(self::once())->method('countVersions')->with('entry')->willReturn(500);
    $result = new ListTimeEntryVersionsHandler($entries, $this->policy())(new ListTimeEntryVersionsQuery('user', 'task', 'entry', 401, 2));
    self::assertSame(500, $result->totalItems);
    self::assertCount(2, $result->versions);
    self::assertSame(399, $result->nextBeforeRevision);
  }

  #[Test]
  public function testHidesAnotherBeneficiaryBeforeReadingItsVersions(): void
  {
    $entries = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $entries->method('context')->willReturn(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []));
    $entries->method('find')->willReturn(new TimeEntryView('entry', 'task', 'other', '2026-01-01', 60, null, 500, false, 'other', 'other', 'now', 'now'));
    $entries->expects(self::never())->method('versions');
    $entries->expects(self::never())->method('countVersions');
    $this->expectException(InterventionNotFoundException::class);
    new ListTimeEntryVersionsHandler($entries, $this->policy())(new ListTimeEntryVersionsQuery('user', 'task', 'entry'));
  }

  #[Test]
  public function testRejectsAnUnboundedWindowAtTheApplicationBoundary(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new ListTimeEntryVersionsQuery('user', 'task', 'entry', null, 101);
  }

  /**
   * Method policy
   *
   * Supplies an active caller without the other-beneficiary management grant.
   *
   * @access private
   *
   * @return InterventionTimeAccessPolicy real policy using mocked outbound ports
   */
  private function policy(): InterventionTimeAccessPolicy
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturn(false);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('actor', 'user', true)]);

    return new InterventionTimeAccessPolicy($authorization, $workforce);
  }
  // #endregion
}
