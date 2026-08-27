<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Response\ResolveInspectionResponseScope;

use Inspection\Application\Contract\Inspection\InspectionScope;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, InterventionScopePort};
use Inspection\Application\UseCase\Query\Response\ResolveInspectionResponseScope\{ResolveInspectionResponseScopeHandler, ResolveInspectionResponseScopeQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ResolveInspectionResponseScopeHandlerTest.
 *
 * The precedence between the three scoping filters is the endpoint's
 * published contract, and getting it wrong is invisible until someone lists
 * the wrong organization's responses.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResolveInspectionResponseScopeHandler::class)]
final class ResolveInspectionResponseScopeHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554400b1';

  private const string INSPECTION_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554400c1';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';
  // #endregion

  // #region Tests
  /**
   * Method testAnExplicitOrganizationWinsAndCostsNoLookup.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnExplicitOrganizationWinsAndCostsNoLookup(): void
  {
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::never())->method('organizationIdOf');
    $inspections = $this->createMock(InspectionRepositoryPort::class);
    $inspections->expects(self::never())->method('findScope');

    $result = (new ResolveInspectionResponseScopeHandler($interventions, $inspections))(
      new ResolveInspectionResponseScopeQuery(
        organizationId: self::ORGANIZATION_ID,
        interventionId: self::INTERVENTION_ID,
        inspectionId: self::INSPECTION_ID,
      ),
    );

    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  /**
   * Method testAnInterventionNamesItsOwnOrganization.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInterventionNamesItsOwnOrganization(): void
  {
    $interventions = $this->createStub(InterventionScopePort::class);
    $interventions->method('organizationIdOf')->willReturn(self::INTERVENTION_ORGANIZATION_ID);
    $inspections = $this->createMock(InspectionRepositoryPort::class);
    $inspections->expects(self::never())->method('findScope');

    $result = (new ResolveInspectionResponseScopeHandler($interventions, $inspections))(
      new ResolveInspectionResponseScopeQuery(
        interventionId: self::INTERVENTION_ID,
        inspectionId: self::INSPECTION_ID,
      ),
    );

    self::assertSame(self::INTERVENTION_ORGANIZATION_ID, $result->organizationId);
  }

  /**
   * Method testAnUnknownInterventionFallsThroughToTheInspection.
   *
   * The inspection filter is a FALLBACK, not an alternative: it only runs
   * when the first two produced nothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownInterventionFallsThroughToTheInspection(): void
  {
    $interventions = $this->createStub(InterventionScopePort::class);
    $interventions->method('organizationIdOf')->willReturn(null);
    $inspections = $this->createStub(InspectionRepositoryPort::class);
    $inspections->method('findScope')->willReturn(
      new InspectionScope(self::INSPECTION_ID, self::INSPECTION_ORGANIZATION_ID, null),
    );

    $result = (new ResolveInspectionResponseScopeHandler($interventions, $inspections))(
      new ResolveInspectionResponseScopeQuery(
        interventionId: self::INTERVENTION_ID,
        inspectionId: self::INSPECTION_ID,
      ),
    );

    self::assertSame(self::INSPECTION_ORGANIZATION_ID, $result->organizationId);
  }

  /**
   * Method testNoFilterResolvesToNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testNoFilterResolvesToNothing(): void
  {
    $result = (new ResolveInspectionResponseScopeHandler(
      $this->createStub(InterventionScopePort::class),
      $this->createStub(InspectionRepositoryPort::class),
    ))(new ResolveInspectionResponseScopeQuery());

    self::assertNull($result->organizationId);
  }

  /**
   * Method testAnUnknownInspectionResolvesToNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownInspectionResolvesToNothing(): void
  {
    $inspections = $this->createStub(InspectionRepositoryPort::class);
    $inspections->method('findScope')->willReturn(null);

    $result = (new ResolveInspectionResponseScopeHandler(
      $this->createStub(InterventionScopePort::class),
      $inspections,
    ))(new ResolveInspectionResponseScopeQuery(inspectionId: self::INSPECTION_ID));

    self::assertNull($result->organizationId);
  }

  /**
   * Method testAMalformedInspectionIdentifierResolvesToNothingRatherThanThrowing.
   *
   * Before the identifier was a value object, `find()` returned null for any
   * unparseable string and the endpoint answered 400.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedInspectionIdentifierResolvesToNothingRatherThanThrowing(): void
  {
    $inspections = $this->createMock(InspectionRepositoryPort::class);
    $inspections->expects(self::never())->method('findScope');

    $result = (new ResolveInspectionResponseScopeHandler(
      $this->createStub(InterventionScopePort::class),
      $inspections,
    ))(new ResolveInspectionResponseScopeQuery(inspectionId: 'not-a-uuid'));

    self::assertNull($result->organizationId);
  }
  // #endregion
}
