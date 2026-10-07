<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Equipment\GetEquipmentInspectionSummary;

use Inspection\Application\Contract\Equipment\EquipmentInspectionSummary;
use Inspection\Application\Port\Outbound\{EquipmentInspectionSummaryPort, EquipmentValidationPort};
use Inspection\Application\UseCase\Query\Equipment\GetEquipmentInspectionSummary\{GetEquipmentInspectionSummaryHandler, GetEquipmentInspectionSummaryQuery};
use Inspection\Domain\Exception\{EquipmentInspectionSummaryAccessDeniedException, EquipmentInspectionSummaryNotFoundException};
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

/**
 * Class GetEquipmentInspectionSummaryHandlerTest
 *
 * Tests both read grants and non-disclosing equipment scope before facts are read.
 *
 * @category Test
 */
final class GetEquipmentInspectionSummaryHandlerTest extends TestCase
{
  private const string ORG = '550e8400-e29b-41d4-a716-448050000001';

  private const string EQUIPMENT = '550e8400-e29b-41d4-a716-448050000002';

  private const string USER = '550e8400-e29b-41d4-a716-448050000003';

  #[Test]
  public function returnsIndependentPublishedFactsAfterBothPermissionsAndOwnership(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))->method('resolveAccess')->with(self::USER, self::ORG, self::callback(static fn (string $permission): bool => in_array($permission, ['organization.inspection.read', 'organization.equipment.read'], true)))->willReturn(OrganizationAccessDecision::GRANTED);
    $equipment = $this->createMock(EquipmentValidationPort::class);
    $equipment->expects(self::once())->method('assertPublishedEquipmentExists')->with(self::EQUIPMENT, self::ORG);
    $summary = new EquipmentInspectionSummary(self::EQUIPMENT, 2, ['low' => 0, 'medium' => 0, 'high' => 1, 'critical' => 1], 'inspection-1', '2026-10-05T12:00:00+00:00', 'pass');
    $summaries = $this->createMock(EquipmentInspectionSummaryPort::class);
    $summaries->expects(self::once())->method('find')->with(self::ORG, self::EQUIPMENT)->willReturn($summary);
    $result = new GetEquipmentInspectionSummaryHandler($authorization, $equipment, $summaries)(new GetEquipmentInspectionSummaryQuery(self::ORG, self::EQUIPMENT, self::USER));
    self::assertSame($summary, $result->summary);
    self::assertSame('pass', $result->summary->lastInspectionResult);
    self::assertSame(2, $result->summary->openAnomalies);
  }

  #[Test]
  public function deniesMissingInspectionReadBeforeEquipmentLookup(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with(self::USER, self::ORG, 'organization.inspection.read')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $equipment = $this->createMock(EquipmentValidationPort::class);
    $equipment->expects(self::never())->method('assertPublishedEquipmentExists');
    $summaries = $this->createMock(EquipmentInspectionSummaryPort::class);
    $summaries->expects(self::never())->method('find');
    $this->expectException(EquipmentInspectionSummaryAccessDeniedException::class);
    new GetEquipmentInspectionSummaryHandler($authorization, $equipment, $summaries)(new GetEquipmentInspectionSummaryQuery(self::ORG, self::EQUIPMENT, self::USER));
  }

  #[Test]
  public function deniesMissingEquipmentReadBeforeEquipmentLookup(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))->method('resolveAccess')->willReturnOnConsecutiveCalls(OrganizationAccessDecision::GRANTED, OrganizationAccessDecision::MISSING_PERMISSION);
    $equipment = $this->createMock(EquipmentValidationPort::class);
    $equipment->expects(self::never())->method('assertPublishedEquipmentExists');
    $summaries = $this->createMock(EquipmentInspectionSummaryPort::class);
    $summaries->expects(self::never())->method('find');
    $this->expectException(EquipmentInspectionSummaryAccessDeniedException::class);
    new GetEquipmentInspectionSummaryHandler($authorization, $equipment, $summaries)(new GetEquipmentInspectionSummaryQuery(self::ORG, self::EQUIPMENT, self::USER));
  }

  #[Test]
  public function hidesOutsideOrganizationWithoutReadingFacts(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $equipment = $this->createMock(EquipmentValidationPort::class);
    $equipment->expects(self::never())->method('assertPublishedEquipmentExists');
    $summaries = $this->createMock(EquipmentInspectionSummaryPort::class);
    $summaries->expects(self::never())->method('find');
    $this->expectException(EquipmentInspectionSummaryNotFoundException::class);
    new GetEquipmentInspectionSummaryHandler($authorization, $equipment, $summaries)(new GetEquipmentInspectionSummaryQuery(self::ORG, self::EQUIPMENT, self::USER));
  }

  #[Test]
  public function turnsUnknownOrForeignEquipmentIntoSameNotFound(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $equipment = $this->createMock(EquipmentValidationPort::class);
    $equipment->expects(self::once())->method('assertPublishedEquipmentExists')->with(self::EQUIPMENT, self::ORG)->willThrowException(new InvalidArgumentException('Unavailable equipment'));
    $summaries = $this->createMock(EquipmentInspectionSummaryPort::class);
    $summaries->expects(self::never())->method('find');
    $this->expectException(EquipmentInspectionSummaryNotFoundException::class);
    new GetEquipmentInspectionSummaryHandler($authorization, $equipment, $summaries)(new GetEquipmentInspectionSummaryQuery(self::ORG, self::EQUIPMENT, self::USER));
  }
}
