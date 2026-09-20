<?php

declare(strict_types=1);

namespace App\Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Workforce\OrganizationWorkforceMember;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionTimeAccessPolicyTest.
 *
 * Covers the authorization policy followed by the journal architecture guard:
 * organization isolation, active membership, dedicated grants and contributor history.
 *
 * @category Unit Tests
 *
 * @version 1.0.0
 */
#[CoversClass(InterventionTimeAccessPolicy::class)]
final class InterventionTimeAccessPolicyTest extends TestCase
{
  // #region Methods
  /**
   * Rejects an outsider before reading organization workforce information.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testActorOutsideOrganizationIsHidden(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('isMemberOf')->with('user', 'org')->willReturn(false);
    $authorization->expects(self::never())->method('hasPermission');
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::never())->method('members');

    $this->expectException(InterventionNotFoundException::class);
    new InterventionTimeAccessPolicy($authorization, $workforce)->actor($this->task(), 'user');
  }

  /**
   * Requires an active member even when the account belongs to the organization.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testInactiveActorCannotResolveJournalAccess(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('actor', 'user', false)]);

    $this->expectException(InterventionNotFoundException::class);
    new InterventionTimeAccessPolicy($authorization, $workforce)->actor($this->task(), 'user');
  }

  /**
   * Denies writes lacking either the dedicated grant or the contribution scope.
   *
   * @since 1.0.0
   *
   * @param bool $canWrite whether the actor has the own-time grant
   * @param string $assignee current task assignee
   * @param string $beneficiary member whose time would be recorded
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('deniedWrites')]
  public function testUnauthorizedWritesAreDenied(bool $canWrite, string $assignee, string $beneficiary): void
  {
    $this->expectException(InterventionAccessDeniedException::class);
    $this->policy($canWrite)->assertWrite($this->task($assignee), 'user', $beneficiary, false);
  }

  /**
   * Cases that must never acquire contributor rights from a broad read or membership.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{bool, string, string}> dedicated grant and contribution denial cases
   */
  public static function deniedWrites(): iterable
  {
    yield 'assigned without time permission' => [false, 'actor', 'actor'];
    yield 'own-time grant does not allow another beneficiary' => [true, 'actor', 'other'];
    yield 'own-time grant does not allow unrelated tasks' => [true, 'other', 'actor'];
  }

  /**
   * Preserves retrospective rights for a former assignee without changing execution rights.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testFormerAssigneeCanRecordTheirOwnWork(): void
  {
    self::assertSame('actor', $this->policy(true)->assertWrite($this->task('other', ['actor']), 'user', 'actor', false));
  }

  /**
   * Existing contributors retain correction rights after assignment changes.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testExistingContributionCanBeCorrected(): void
  {
    self::assertSame('actor', $this->policy(true)->assertWrite($this->task('other'), 'user', 'actor', true));
  }

  /**
   * Retained history never overrides a revoked time-writing permission.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testExistingContributionDoesNotBypassPermissionLoss(): void
  {
    $this->expectException(InterventionAccessDeniedException::class);
    $this->policy(false)->assertWrite($this->task('other', ['actor']), 'user', 'actor', true);
  }

  /**
   * Management permission keeps the acting author distinct from the beneficiary.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testManagerCanWriteForAnotherActiveMember(): void
  {
    self::assertSame('actor', $this->policy(false, true)->assertWrite($this->task('other'), 'user', 'other', false));
  }

  /**
   * Administrative permission cannot revive an inactive beneficiary.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testManagerCannotWriteForInactiveBeneficiary(): void
  {
    $this->expectException(InterventionNotFoundException::class);
    $this->policy(false, true, false)->assertWrite($this->task('other'), 'user', 'other', false);
  }

  /**
   * Builds the policy with organization-scoped port responses, not mocked policy decisions.
   *
   * @since 1.0.0
   *
   * @param bool $canWrite own-time grant
   * @param bool $canManage others' time-management grant
   * @param bool $beneficiaryActive whether the second member is active
   *
   * @return InterventionTimeAccessPolicy policy using the real authorization logic
   */
  private function policy(bool $canWrite, bool $canManage = false, bool $beneficiaryActive = true): InterventionTimeAccessPolicy
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturnMap([['user', 'org', true]]);
    $authorization->method('hasPermission')->willReturnMap([
      ['user', 'org', 'organization.interventions.time.write', $canWrite],
      ['user', 'org', 'organization.interventions.time.manage', $canManage],
    ]);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturnMap([['org', [
      new OrganizationWorkforceMember('actor', 'user', true),
      new OrganizationWorkforceMember('other', 'other-user', $beneficiaryActive),
    ]]]);

    return new InterventionTimeAccessPolicy($authorization, $workforce);
  }

  /**
   * Builds an unambiguous task scope with optional historical assignees.
   *
   * @since 1.0.0
   *
   * @param string $assignee current member assignment
   * @param list<string> $previousAssignees retained assignment history
   *
   * @return TimeEntryTaskContext scoped task used by the policy
   */
  private function task(string $assignee = 'actor', array $previousAssignees = []): TimeEntryTaskContext
  {
    return new TimeEntryTaskContext('task', 'intervention', 'org', $assignee, null, [], $previousAssignees);
  }
  // #endregion
}
