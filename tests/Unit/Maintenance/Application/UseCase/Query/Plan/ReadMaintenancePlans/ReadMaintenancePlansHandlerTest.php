<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Query\Plan\ReadMaintenancePlans;

use DateTimeImmutable;
use Intervention\Application\Port\Inbound\InterventionMaintenanceWorkPort;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenancePlanState};
use Maintenance\Application\Port\Outbound\Plan\MaintenancePlanStorePort;
use Maintenance\Application\UseCase\Query\Plan\ReadMaintenancePlans\{ReadMaintenancePlansHandler, ReadMaintenancePlansQuery, ReadMaintenancePlansResult};
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Class ReadMaintenancePlansHandlerTest
 *
 * Verifies bounded, scoped reads that never change engine authority or schedules.
 *
 * @category Tests
 */
#[CoversClass(ReadMaintenancePlansHandler::class)]
final class ReadMaintenancePlansHandlerTest extends TestCase
{
  // #region Constants
  /**
   * Constant ORGANIZATION
   */
  private const string ORGANIZATION = '018fa001-1111-7111-8111-111111111111';

  /**
   * Constant ACTOR
   */
  private const string ACTOR = '018fa002-1111-7111-8111-111111111111';

  /**
   * Constant PLAN
   */
  private const string PLAN = '018fa003-1111-7111-8111-111111111111';

  /**
   * Constant EQUIPMENT
   */
  private const string EQUIPMENT = '018fa004-1111-7111-8111-111111111111';

  /**
   * Constant INTERVENTION
   */
  private const string INTERVENTION = '018fa005-1111-7111-8111-111111111111';
  // #endregion

  // #region Properties
  /** Property plans @var MaintenancePlanStorePort&MockObject */
  private MaintenancePlanStorePort&MockObject $plans;

  /** Property work @var InterventionMaintenanceWorkPort&MockObject */
  private InterventionMaintenanceWorkPort&MockObject $work;

  /** Property authorization @var OrganizationAuthorizationPort&MockObject */
  private OrganizationAuthorizationPort&MockObject $authorization;
  // #endregion

  // #region Methods
  /**
   * Method setUp
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    $this->plans = $this->createMock(MaintenancePlanStorePort::class);
    $this->work = $this->createMock(InterventionMaintenanceWorkPort::class);
    $this->authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $this->plans->expects(self::never())->method('save');
    $this->plans->expects(self::never())->method('saveOccurrence');
    $this->plans->expects(self::never())->method('activateEngine');
    $this->plans->expects(self::never())->method('synchronized');
    $this->work->expects(self::never())->method('attachOccurrence');
  }

  /**
   * Method testAuthorizedDetailReturnsCurrentStateWithoutWriting
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testAuthorizedDetailReturnsCurrentStateWithoutWriting(): void
  {
    $this->allow();
    $plan = $this->plan();
    $this->plans->expects(self::once())->method('engineMode')->with(self::ORGANIZATION)->willReturn('plans');
    $this->plans->expects(self::once())->method('find')->with(self::ORGANIZATION, self::PLAN)->willReturn($plan);
    $this->plans->expects(self::once())->method('openOccurrence')->with(self::ORGANIZATION, self::PLAN)->willReturn(null);
    $this->plans->expects(self::never())->method('list');
    $this->plans->expects(self::never())->method('count');

    $result = $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'detail', self::PLAN));

    self::assertInstanceOf(ReadMaintenancePlansResult::class, $result);
    self::assertSame('plans', $result->mode);
    self::assertSame(1, $result->total);
    self::assertCount(1, $result->items);
    self::assertSame($plan, $result->items[0]->plan);
    self::assertNull($result->items[0]->openOccurrence);
    self::assertFalse($result->items[0]->retryAllowed);
    self::assertSame([], $result->dates);
  }

  /**
   * Method testPreviewReturnsThreeAnchoredDatesAndDoesNotActivateEngine
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPreviewReturnsThreeAnchoredDatesAndDoesNotActivateEngine(): void
  {
    $this->allow();
    $plan = $this->plan();
    $plan->nextDueAt = new DateTimeImmutable('2026-02-28T00:00:00+00:00');
    $this->plans->expects(self::once())->method('engineMode')->with(self::ORGANIZATION)->willReturn('legacy');
    $this->plans->expects(self::once())->method('find')->with(self::ORGANIZATION, self::PLAN)->willReturn($plan);
    $this->plans->expects(self::once())->method('openOccurrence')->with(self::ORGANIZATION, self::PLAN)->willReturn(null);

    $result = $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'preview', self::PLAN));

    self::assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $result->dates));
    self::assertSame('legacy', $result->mode);
    self::assertSame('2026-01-31', $plan->anchorAt?->format('Y-m-d'));
    self::assertSame('2026-02-28', $plan->nextDueAt->format('Y-m-d'));
    self::assertFalse($plan->active);
  }

  /**
   * Method testHistoricalUnscheduledPlanHasNoInventedPreview
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testHistoricalUnscheduledPlanHasNoInventedPreview(): void
  {
    $this->allow();
    $plan = $this->plan();
    $plan->cadenceMode = 'legacy';
    $plan->interval = 'P1Y6M';
    $plan->anchorAt = null;
    $plan->nextDueAt = null;
    $this->plans->expects(self::once())->method('engineMode')->willReturn('legacy');
    $this->plans->expects(self::once())->method('find')->with(self::ORGANIZATION, self::PLAN)->willReturn($plan);
    $this->plans->expects(self::once())->method('openOccurrence')->willReturn(null);

    $result = $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'preview', self::PLAN));

    self::assertSame([], $result->dates);
    self::assertNull($plan->nextDueAt);
  }

  /**
   * Method testFilteredListUsesBoundedPageAndMatchingCount
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFilteredListUsesBoundedPageAndMatchingCount(): void
  {
    $this->allow();
    $plan = $this->plan();
    $this->plans->expects(self::once())->method('engineMode')->with(self::ORGANIZATION)->willReturn('plans');
    $this->plans->expects(self::once())->method('list')->with(self::ORGANIZATION, 20, 40, self::EQUIPMENT, 'maintenance', 'extinguisher')->willReturn([$plan]);
    $this->plans->expects(self::once())->method('count')->with(self::ORGANIZATION, self::EQUIPMENT, 'maintenance', 'extinguisher')->willReturn(61);
    $this->plans->expects(self::once())->method('openOccurrence')->with(self::ORGANIZATION, self::PLAN)->willReturn(null);
    $this->plans->expects(self::never())->method('find');

    $result = $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, page: 3, itemsPerPage: 20, equipmentId: self::EQUIPMENT, operationKind: 'maintenance', search: 'extinguisher'));

    self::assertSame(61, $result->total);
    self::assertSame($plan, $result->items[0]->plan);
  }

  /**
   * Method testListClampsPagination
   *
   * @access public
   *
   * @param int $page the requested page
   * @param int $size the requested page size
   * @param int $expectedSize the bounded page size
   * @param int $offset the bounded offset
   *
   * @return void
   */
  #[Test]
  #[DataProvider('paginationCases')]
  public function testListClampsPagination(int $page, int $size, int $expectedSize, int $offset): void
  {
    $this->allow();
    $this->plans->expects(self::once())->method('engineMode')->willReturn('legacy');
    $this->plans->expects(self::once())->method('list')->with(self::ORGANIZATION, $expectedSize, $offset, null, null, null)->willReturn([]);
    $this->plans->expects(self::once())->method('count')->with(self::ORGANIZATION, null, null, null)->willReturn(0);

    $result = $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, page: $page, itemsPerPage: $size));

    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
  }

  /**
   * Method paginationCases
   *
   * @access public
   *
   * @return iterable<string, array{int, int, int, int}> pagination limits
   */
  public static function paginationCases(): iterable
  {
    yield 'negative page and large size' => [-3, 500, 100, 0];
    yield 'non-positive size' => [2, 0, 1, 1];
  }

  /**
   * Method testEngineReadReturnsPreparedCountWithoutCreatingPlans
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEngineReadReturnsPreparedCountWithoutCreatingPlans(): void
  {
    $this->allow();
    $this->plans->expects(self::once())->method('engineMode')->with(self::ORGANIZATION)->willReturn('legacy');
    $this->plans->expects(self::once())->method('count')->with(self::ORGANIZATION)->willReturn(12);
    $this->plans->expects(self::never())->method('find');
    $this->plans->expects(self::never())->method('list');

    $result = $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'engine'));

    self::assertSame('legacy', $result->mode);
    self::assertSame(12, $result->preparedCount);
    self::assertSame([], $result->items);
  }

  /**
   * Method testMissingPermissionDeniesBeforeReadingPlans
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testMissingPermissionDeniesBeforeReadingPlans(): void
  {
    $this->allow(OrganizationAccessDecision::MISSING_PERMISSION);
    $this->plans->expects(self::never())->method('engineMode');
    $this->plans->expects(self::never())->method('find');
    $this->plans->expects(self::never())->method('list');
    $this->expectException(MaintenanceAccessDeniedException::class);

    $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'detail', self::PLAN));
  }

  /**
   * Method testOutsideOrganizationIsHiddenBeforeReadingPlans
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOutsideOrganizationIsHiddenBeforeReadingPlans(): void
  {
    $this->allow(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $this->plans->expects(self::never())->method('engineMode');
    $this->plans->expects(self::never())->method('find');
    $this->plans->expects(self::never())->method('list');
    $this->expectException(MaintenanceNotFoundException::class);

    $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'detail', self::PLAN));
  }

  /**
   * Method testUnknownPlanReturnsNotFoundInsideAuthorizedOrganization
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownPlanReturnsNotFoundInsideAuthorizedOrganization(): void
  {
    $this->allow();
    $this->plans->expects(self::once())->method('engineMode')->willReturn('plans');
    $this->plans->expects(self::once())->method('find')->with(self::ORGANIZATION, self::PLAN)->willReturn(null);
    $this->plans->expects(self::never())->method('openOccurrence');
    $this->expectException(MaintenanceNotFoundException::class);

    $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'detail', self::PLAN));
  }

  /**
   * Method testUnknownOperationKindIsRejected
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownOperationKindIsRejected(): void
  {
    $this->allow();
    $this->plans->expects(self::never())->method('engineMode');
    $this->plans->expects(self::never())->method('list');
    $this->expectException(MaintenanceValidationException::class);

    $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, operationKind: 'repair'));
  }

  /**
   * Method testRetryDecisionComesFromCurrentAttemptAndWorkStatus
   *
   * @access public
   *
   * @param ?string $resultId the previous failed result, if any
   * @param string $workStatus the current work state
   * @param bool $expected whether the server permits an explicit retry
   *
   * @return void
   */
  #[Test]
  #[DataProvider('retryCases')]
  public function testRetryDecisionComesFromCurrentAttemptAndWorkStatus(?string $resultId, string $workStatus, bool $expected): void
  {
    $this->allow();
    $this->plans->expects(self::once())->method('engineMode')->willReturn('plans');
    $this->plans->expects(self::once())->method('find')->with(self::ORGANIZATION, self::PLAN)->willReturn($this->plan());
    $open = new MaintenanceOccurrenceState('018fa006-1111-7111-8111-111111111111', self::PLAN, self::ORGANIZATION, new DateTimeImmutable('2026-01-31'), 'open', 1, self::INTERVENTION, null, $resultId, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01'));
    $this->plans->expects(self::once())->method('openOccurrence')->with(self::ORGANIZATION, self::PLAN)->willReturn($open);
    $this->work->expects(null === $resultId ? self::once() : self::never())->method('status')->with(self::ORGANIZATION, self::INTERVENTION)->willReturn($workStatus);

    $result = $this->handler()(new ReadMaintenancePlansQuery(self::ORGANIZATION, self::ACTOR, 'detail', self::PLAN));

    self::assertSame($open, $result->items[0]->openOccurrence);
    self::assertSame($expected, $result->items[0]->retryAllowed);
  }

  /**
   * Method retryCases
   *
   * @access public
   *
   * @return iterable<string, array{?string, string, bool}> retry conditions
   */
  public static function retryCases(): iterable
  {
    yield 'abandoned attempt' => [null, 'abandoned', true];
    yield 'running attempt' => [null, 'in_progress', false];
    yield 'failed operation result' => ['018fa007-1111-7111-8111-111111111111', 'published', true];
  }

  /**
   * Method allow
   *
   * @access private
   *
   * @param OrganizationAccessDecision $decision the scoped access decision
   *
   * @return void
   */
  private function allow(OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED): void
  {
    $this->authorization->expects(self::once())->method('resolveAccess')->with(self::ACTOR, self::ORGANIZATION, 'organization.maintenance.read')->willReturn($decision);
  }

  /**
   * Method handler
   *
   * @access private
   *
   * @return ReadMaintenancePlansHandler the handler with mocked ports
   */
  private function handler(): ReadMaintenancePlansHandler
  {
    return new ReadMaintenancePlansHandler($this->plans, $this->work, $this->authorization);
  }

  /**
   * Method plan
   *
   * @access private
   *
   * @return MaintenancePlanState a prepared monthly plan
   */
  private function plan(): MaintenancePlanState
  {
    return new MaintenancePlanState(
      self::PLAN,
      self::ORGANIZATION,
      self::EQUIPMENT,
      null,
      'fire_extinguisher',
      'Monthly service',
      'maintenance',
      'P1M',
      'fixed',
      new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
      false,
      null,
      null,
      null,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
