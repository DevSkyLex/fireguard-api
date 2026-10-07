<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\Contract\Equipment\EquipmentInspectionSummary;
use Inspection\Application\UseCase\Query\Equipment\GetEquipmentInspectionSummary\{GetEquipmentInspectionSummaryQuery, GetEquipmentInspectionSummaryResult};
use Inspection\Presentation\Api\Provider\Equipment\GetEquipmentInspectionSummaryProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class GetEquipmentInspectionSummaryProviderTest
 *
 * Verifies HTTP translation retains all summary axes and authenticated actor.
 *
 * @category Test
 */
final class GetEquipmentInspectionSummaryProviderTest extends TestCase
{
  #[Test]
  public function mapsScopedFactsAndActualInspectionInstant(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(new SecurityUser('user', 'user@example.com', 'password'));
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (GetEquipmentInspectionSummaryQuery $query): bool => 'org' === $query->organizationId && 'equipment' === $query->equipmentId && 'user' === $query->userId))->willReturn(new GetEquipmentInspectionSummaryResult(new EquipmentInspectionSummary('equipment', 1, ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 1], 'inspection', '2026-10-05T14:30:00+00:00', 'pass')));
    $output = new GetEquipmentInspectionSummaryProvider($queries, $security)->provide(new Get(), ['organizationId' => 'org', 'equipmentId' => 'equipment']);
    self::assertSame('equipment', $output->equipmentId);
    self::assertSame(1, $output->openAnomalies);
    self::assertSame('pass', $output->lastInspectionResult);
    self::assertSame('2026-10-05T14:30:00+00:00', $output->lastInspectionPerformedAt);
  }

  #[Test]
  public function unauthenticatedCallNeverDispatches(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    new GetEquipmentInspectionSummaryProvider($queries, $security)->provide(new Get(), ['organizationId' => 'org', 'equipmentId' => 'equipment']);
  }
}
