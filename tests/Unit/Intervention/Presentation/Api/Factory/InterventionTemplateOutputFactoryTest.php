<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use DateTimeImmutable;
use Intervention\Application\Contract\Template\{InterventionTemplateItemView, InterventionTemplateView};
use Intervention\Presentation\Api\Factory\InterventionTemplateOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionTemplateOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionTemplateOutputFactory::class)]
final class InterventionTemplateOutputFactoryTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440002';

  // #region Methods
  #[Test]
  public function testFromViewMapsScalarsAndBuildsTheOptionalIris(): void
  {
    $view = $this->view(
      defaultSiteId: '550e8400-e29b-41d4-a716-446655440005',
      defaultResponsibleId: '550e8400-e29b-41d4-a716-446655440006',
      items: [],
    );

    $output = new InterventionTemplateOutputFactory()->fromView($view);

    self::assertSame('550e8400-e29b-41d4-a716-446655440003', $output->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    self::assertSame('Quarterly audit', $output->name);
    self::assertSame('Runs every quarter.', $output->description);
    self::assertSame('inventory', $output->type);
    self::assertSame('high', $output->priority);
    self::assertSame('/api/facilities/550e8400-e29b-41d4-a716-446655440005', $output->defaultSite);
    self::assertSame(
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/550e8400-e29b-41d4-a716-446655440006',
      $output->defaultResponsible,
    );
    self::assertSame('P14D', $output->duration);
    self::assertSame(['550e8400-e29b-41d4-a716-446655440007'], $output->labelIds);
    self::assertSame('2026-01-01T08:30:00+00:00', $output->createdAt);
    self::assertSame('2026-02-03T09:45:00+00:00', $output->updatedAt);
    self::assertSame([], $output->items);
  }

  #[Test]
  public function testFromViewLeavesTheOptionalIrisNullWhenTheViewHasNoReferences(): void
  {
    $output = new InterventionTemplateOutputFactory()->fromView($this->view(null, null, []));

    self::assertNull($output->defaultSite);
    self::assertNull($output->defaultResponsible);
  }

  #[Test]
  public function testFromViewMapsTheTemplateItems(): void
  {
    $items = [
      new InterventionTemplateItemView(
        '550e8400-e29b-41d4-a716-446655440008',
        1,
        'inspection',
        '/api/facilities/550e8400-e29b-41d4-a716-446655440009',
        '/api/inspections/550e8400-e29b-41d4-a716-44665544000a',
        false,
        '550e8400-e29b-41d4-a716-44665544000b',
      ),
      new InterventionTemplateItemView('550e8400-e29b-41d4-a716-44665544000c', 2, 'inventory', null, null, true, null),
    ];

    $output = new InterventionTemplateOutputFactory()->fromView($this->view(null, null, $items));

    self::assertCount(2, $output->items);

    $first = $output->items[0];
    self::assertSame('550e8400-e29b-41d4-a716-446655440008', $first->id);
    self::assertSame(1, $first->position);
    self::assertSame('inspection', $first->action);
    self::assertSame('/api/facilities/550e8400-e29b-41d4-a716-446655440009', $first->target);
    self::assertSame('/api/inspections/550e8400-e29b-41d4-a716-44665544000a', $first->resultResource);
    self::assertFalse($first->required);
    self::assertSame(
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/550e8400-e29b-41d4-a716-44665544000b',
      $first->defaultAssignee,
    );

    $second = $output->items[1];
    self::assertSame(2, $second->position);
    self::assertTrue($second->required);
    self::assertNull($second->target);
    self::assertNull($second->resultResource);
    self::assertNull($second->defaultAssignee);
  }

  /**
   * @param list<InterventionTemplateItemView> $items
   */
  private function view(?string $defaultSiteId, ?string $defaultResponsibleId, array $items): InterventionTemplateView
  {
    return new InterventionTemplateView(
      '550e8400-e29b-41d4-a716-446655440003',
      self::ORGANIZATION_ID,
      'Quarterly audit',
      'Runs every quarter.',
      'inventory',
      'high',
      $defaultSiteId,
      $defaultResponsibleId,
      'P14D',
      ['550e8400-e29b-41d4-a716-446655440007'],
      $items,
      new DateTimeImmutable('2026-01-01T08:30:00+00:00'),
      new DateTimeImmutable('2026-02-03T09:45:00+00:00'),
    );
  }
  // #endregion
}
