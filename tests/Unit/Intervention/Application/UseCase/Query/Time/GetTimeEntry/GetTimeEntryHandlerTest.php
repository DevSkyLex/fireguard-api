<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Time\GetTimeEntry;

use Intervention\Application\Contract\Time\{TimeEntryTaskContext, TimeEntryView};
use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Application\UseCase\Query\Time\GetTimeEntry\{GetTimeEntryHandler, GetTimeEntryQuery};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Organization\Application\Contract\Workforce\OrganizationWorkforceMember;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class GetTimeEntryHandlerTest
 *
 * Covers direct current-entry reads and hidden foreign beneficiaries.
 *
 * @category Test
 */
#[CoversClass(GetTimeEntryHandler::class)]
final class GetTimeEntryHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCurrentReadDoesNotWalkJournalOrHistoryPages(): void
  {
    $entries = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $entries->method('context')->willReturn(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []));
    $entry = new TimeEntryView('entry', 'task', 'actor', '2026-01-01', 60, null, 500, false, 'actor', 'actor', 'now', 'now');
    $entries->expects(self::once())->method('find')->with('entry')->willReturn($entry);
    $entries->expects(self::never())->method('list');
    $entries->expects(self::never())->method('versions');
    $result = new GetTimeEntryHandler($entries, $this->policy())(new GetTimeEntryQuery('user', 'task', 'entry'));
    self::assertSame($entry, $result->entry);
  }

  #[Test]
  public function testOtherBeneficiaryRemainsHidden(): void
  {
    $entries = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $entries->method('context')->willReturn(new TimeEntryTaskContext('task', 'intervention', 'org', 'actor', null, [], []));
    $entries->expects(self::once())->method('find')->with('entry')->willReturn(new TimeEntryView('entry', 'task', 'other', '2026-01-01', 60, null, 500, false, 'other', 'other', 'now', 'now'));
    $this->expectException(InterventionNotFoundException::class);
    new GetTimeEntryHandler($entries, $this->policy())(new GetTimeEntryQuery('user', 'task', 'entry'));
  }

  /**
   * Method policy
   *
   * Supplies one active member with own-journal visibility.
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
