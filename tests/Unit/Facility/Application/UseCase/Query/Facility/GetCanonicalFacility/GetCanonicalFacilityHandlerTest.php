<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetCanonicalFacility;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\CanonicalFacilityRepositoryPort;
use Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\{GetCanonicalFacilityHandler, GetCanonicalFacilityQuery};
use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityRecordStatus, FacilityStatus, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCanonicalFacilityHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCanonicalFacilityHandler::class)]
final class GetCanonicalFacilityHandlerTest extends TestCase
{
  // #region Constants
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440041';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440042';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440044';
  // #endregion

  // #region Tests
  /**
   * Method testProjectsTheGateInputs.
   *
   * @return void no return value
   */
  #[Test]
  public function testProjectsTheGateInputs(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());

    $result = (new GetCanonicalFacilityHandler($facilities))(
      new GetCanonicalFacilityQuery(self::FACILITY_ID),
    );

    self::assertNotNull($result->view);
    self::assertSame(self::FACILITY_ID, $result->view->id);
    self::assertSame(self::ORGANIZATION_ID, $result->view->organizationId);
    self::assertSame('draft', $result->view->recordStatus);
    self::assertSame(self::INTERVENTION_ID, $result->view->interventionId);
    self::assertSame(3, $result->view->revision);
  }

  /**
   * Method testAnUnknownIdentifierAnswersAnEmptyView.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownIdentifierAnswersAnEmptyView(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn(null);

    self::assertNull(
      (new GetCanonicalFacilityHandler($facilities))(new GetCanonicalFacilityQuery(self::FACILITY_ID))->view,
    );
  }

  /**
   * Method testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing.
   *
   * This is what keeps a canonical mutation on a garbage id a 404 now that
   * the identifier is a validated UUID value object. The empty-string case is
   * the one the processor produces for a non-string URI variable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->expects(self::never())->method('findById');
    $handler = new GetCanonicalFacilityHandler($facilities);

    self::assertNull($handler(new GetCanonicalFacilityQuery('not-a-uuid'))->view);
    self::assertNull($handler(new GetCanonicalFacilityQuery(''))->view);
  }
  // #endregion

  // #region Helpers
  /**
   * Method facility.
   *
   * @return CanonicalFacility a scratchpad site at revision 3
   */
  private function facility(): CanonicalFacility
  {
    return CanonicalFacility::reconstitute(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      recordStatus: FacilityRecordStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
      parentFacilityId: null,
      type: FacilityType::SITE,
      name: 'Main site',
      code: null,
      address: null,
      latitude: null,
      longitude: null,
      metadata: [],
      status: FacilityStatus::ACTIVE,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
