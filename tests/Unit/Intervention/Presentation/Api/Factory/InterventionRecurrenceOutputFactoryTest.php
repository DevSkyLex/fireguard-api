<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Presentation\Api\Factory\InterventionRecurrenceOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionRecurrenceOutputFactoryTest.
 *
 * A recurrence that inherits its site and responsible from the template must
 * expose null relations rather than IRIs built from an empty identifier, and
 * the responsible IRI is organization-scoped — a flat member IRI would 404.
 *
 * @category Factory Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceOutputFactory::class)]
final class InterventionRecurrenceOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string RECURRENCE_ID = '550e8400-e29b-41d4-a716-446655486001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655486002';

  private const string TEMPLATE_ID = '550e8400-e29b-41d4-a716-446655486003';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655486004';

  private const string RESPONSIBLE_ID = '550e8400-e29b-41d4-a716-446655486005';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromViewBuildsEveryRelationIri(): void
  {
    $output = new InterventionRecurrenceOutputFactory()->fromView($this->view(
      siteId: self::SITE_ID,
      responsibleId: self::RESPONSIBLE_ID,
    ));

    self::assertSame(self::RECURRENCE_ID, $output->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    self::assertSame('/api/intervention-templates/' . self::TEMPLATE_ID, $output->template);
    self::assertSame('/api/facilities/' . self::SITE_ID, $output->site);
    self::assertSame(
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::RESPONSIBLE_ID,
      $output->responsible,
    );
  }

  #[Test]
  public function testFromViewLeavesInheritedOverridesNull(): void
  {
    $output = new InterventionRecurrenceOutputFactory()->fromView($this->view());

    self::assertNull($output->site);
    self::assertNull($output->responsible);
    self::assertNull($output->lastMaterializedAt);
    self::assertNull($output->endAt);
  }

  #[Test]
  public function testFromViewCarriesTheRuleAndFormatsItsDates(): void
  {
    $output = new InterventionRecurrenceOutputFactory()->fromView($this->view(
      lastMaterializedAt: new DateTimeImmutable('2026-02-15T00:00:00+00:00'),
      endAt: new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
    ));

    self::assertSame('Quarterly round', $output->name);
    self::assertSame('quarterly', $output->frequency);
    self::assertSame(2, $output->interval);
    self::assertSame('Europe/Paris', $output->timezone);
    self::assertSame(10, $output->leadTimeDays);
    self::assertTrue($output->isActive);
    self::assertSame('2026-01-15T00:00:00+00:00', $output->anchorDate);
    self::assertSame('2026-04-15T00:00:00+00:00', $output->nextOccurrenceAt);
    self::assertSame('2026-02-15T00:00:00+00:00', $output->lastMaterializedAt);
    self::assertSame('2027-01-01T00:00:00+00:00', $output->endAt);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
    self::assertSame('2026-01-02T00:00:00+00:00', $output->updatedAt);
  }

  private function view(
    ?string $siteId = null,
    ?string $responsibleId = null,
    ?DateTimeImmutable $lastMaterializedAt = null,
    ?DateTimeImmutable $endAt = null,
  ): InterventionRecurrenceView {
    return new InterventionRecurrenceView(
      id: self::RECURRENCE_ID,
      organizationId: self::ORGANIZATION_ID,
      templateId: self::TEMPLATE_ID,
      name: 'Quarterly round',
      siteId: $siteId,
      responsibleId: $responsibleId,
      frequency: 'quarterly',
      interval: 2,
      anchorDate: new DateTimeImmutable('2026-01-15T00:00:00+00:00'),
      timezone: 'Europe/Paris',
      leadTimeDays: 10,
      nextOccurrenceAt: new DateTimeImmutable('2026-04-15T00:00:00+00:00'),
      lastMaterializedAt: $lastMaterializedAt,
      isActive: true,
      endAt: $endAt,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }
  // #endregion
}
