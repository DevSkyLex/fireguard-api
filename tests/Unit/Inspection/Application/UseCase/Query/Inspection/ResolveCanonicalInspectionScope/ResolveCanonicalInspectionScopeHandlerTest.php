<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\ResolveCanonicalInspectionScope;

use Inspection\Application\Port\Outbound\InterventionScopePort;
use Inspection\Application\UseCase\Query\Inspection\ResolveCanonicalInspectionScope\{ResolveCanonicalInspectionScopeHandler, ResolveCanonicalInspectionScopeQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ResolveCanonicalInspectionScopeHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResolveCanonicalInspectionScopeHandler::class)]
final class ResolveCanonicalInspectionScopeHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string INTERVENTION_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554400b2';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';
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

    $result = (new ResolveCanonicalInspectionScopeHandler($interventions))(
      new ResolveCanonicalInspectionScopeQuery(self::ORGANIZATION_ID, self::INTERVENTION_ID),
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

    $result = (new ResolveCanonicalInspectionScopeHandler($interventions))(
      new ResolveCanonicalInspectionScopeQuery(interventionId: self::INTERVENTION_ID),
    );

    self::assertSame(self::INTERVENTION_ORGANIZATION_ID, $result->organizationId);
  }

  /**
   * Method testNoFilterAndAnUnknownInterventionBothResolveToNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testNoFilterAndAnUnknownInterventionBothResolveToNothing(): void
  {
    $interventions = $this->createStub(InterventionScopePort::class);
    $interventions->method('organizationIdOf')->willReturn(null);
    $handler = new ResolveCanonicalInspectionScopeHandler($interventions);

    self::assertNull($handler(new ResolveCanonicalInspectionScopeQuery())->organizationId);
    self::assertNull($handler(new ResolveCanonicalInspectionScopeQuery(interventionId: self::INTERVENTION_ID))->organizationId);
  }
  // #endregion
}
