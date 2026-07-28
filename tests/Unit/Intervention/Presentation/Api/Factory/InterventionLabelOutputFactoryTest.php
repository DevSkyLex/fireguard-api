<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use DateTimeImmutable;
use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Presentation\Api\Factory\InterventionLabelOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionLabelOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionLabelOutputFactory::class)]
final class InterventionLabelOutputFactoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromViewMapsEveryFieldAndBuildsTheOrganizationIri(): void
  {
    $view = new InterventionLabelView(
      '550e8400-e29b-41d4-a716-446655440001',
      '550e8400-e29b-41d4-a716-446655440002',
      'Urgent',
      '#3b82f6',
      new DateTimeImmutable('2026-01-01T08:30:00+00:00'),
      new DateTimeImmutable('2026-02-03T09:45:00+00:00'),
    );

    $output = new InterventionLabelOutputFactory()->fromView($view);

    self::assertSame('550e8400-e29b-41d4-a716-446655440001', $output->id);
    self::assertSame('/api/organizations/550e8400-e29b-41d4-a716-446655440002', $output->organization);
    self::assertSame('Urgent', $output->name);
    self::assertSame('#3b82f6', $output->color);
    self::assertSame('2026-01-01T08:30:00+00:00', $output->createdAt);
    self::assertSame('2026-02-03T09:45:00+00:00', $output->updatedAt);
  }
  // #endregion
}
