<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Factory;

use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class EquipmentOutputFactoryTest
 *
 * Keeps control and service projections independent across result-to-HTTP mapping.
 *
 * @category Unit Tests
 */
final class EquipmentOutputFactoryTest extends TestCase
{
  // #region Methods
  /**
   * Method preservesIndependentDueValuesAndTheCompatibilityControlAlias
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function preservesIndependentDueValuesAndTheCompatibilityControlAlias(): void
  {
    $view = new GetEquipmentResult(
      'equipment',
      'organization',
      null,
      'fire_extinguisher',
      null,
      null,
      null,
      null,
      null,
      'operational',
      null,
      null,
      [],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
      maintenanceDueStatus: 'overdue',
      controlDueStatus: 'up_to_date',
      serviceDueStatus: 'overdue',
      controlNextDueAt: '2027-01-01T00:00:00+00:00',
      serviceNextDueAt: '2026-09-01T00:00:00+00:00',
    );
    $output = new EquipmentOutputFactory()->fromView($view);
    self::assertSame('overdue', $view->maintenanceDueStatus);
    self::assertSame('up_to_date', $view->controlDueStatus);
    self::assertSame('up_to_date', $output->controlDueStatus);
    self::assertSame($output->controlDueStatus, $output->maintenanceDueStatus);
    self::assertSame('overdue', $output->serviceDueStatus);
    self::assertSame('2027-01-01T00:00:00+00:00', $output->controlNextDueAt);
    self::assertSame('2026-09-01T00:00:00+00:00', $output->serviceNextDueAt);
    self::assertSame('operational', $output->status);
  }

  /**
   * Method preservesDefaultsForCommandResultsWithoutDeadlineProjection
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function preservesDefaultsForCommandResultsWithoutDeadlineProjection(): void
  {
    $view = new CreateEquipmentResult(
      'equipment',
      'organization',
      null,
      'fire_extinguisher',
      null,
      null,
      null,
      null,
      null,
      'in_stock',
      null,
      null,
      [],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
    $output = new EquipmentOutputFactory()->fromView($view);
    self::assertSame('unscheduled', $output->maintenanceDueStatus);
    self::assertSame('unscheduled', $output->controlDueStatus);
    self::assertSame('unscheduled', $output->serviceDueStatus);
    self::assertNull($output->controlNextDueAt);
    self::assertNull($output->serviceNextDueAt);
  }
  // #endregion
}
