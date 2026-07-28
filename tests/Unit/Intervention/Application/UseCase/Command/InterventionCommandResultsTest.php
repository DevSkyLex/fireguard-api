<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command;

use DateTimeImmutable;
use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\UseCase\Command\Label\CreateInterventionLabel\CreateInterventionLabelResult;
use Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel\UpdateInterventionLabelResult;
use Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate\CreateInterventionTemplateResult;
use Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate\InstantiateInterventionTemplateResult;
use Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate\UpdateInterventionTemplateResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test InterventionCommandResultsTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateInterventionLabelResult::class)]
#[CoversClass(UpdateInterventionLabelResult::class)]
#[CoversClass(CreateInterventionTemplateResult::class)]
#[CoversClass(UpdateInterventionTemplateResult::class)]
#[CoversClass(InstantiateInterventionTemplateResult::class)]
final class InterventionCommandResultsTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateInterventionLabelResultCarriesTheLabelView(): void
  {
    $view = self::labelView();
    $result = new CreateInterventionLabelResult($view);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($view, $result->label);
  }

  #[Test]
  public function testUpdateInterventionLabelResultCarriesTheLabelView(): void
  {
    $view = self::labelView();
    $result = new UpdateInterventionLabelResult($view);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($view, $result->label);
  }

  #[Test]
  public function testCreateInterventionTemplateResultCarriesTheTemplateView(): void
  {
    $view = self::templateView();
    $result = new CreateInterventionTemplateResult($view);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($view, $result->template);
  }

  #[Test]
  public function testUpdateInterventionTemplateResultCarriesTheTemplateView(): void
  {
    $view = self::templateView();
    $result = new UpdateInterventionTemplateResult($view);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($view, $result->template);
  }

  #[Test]
  public function testInstantiateInterventionTemplateResultCarriesTheCreatedIntervention(): void
  {
    $result = new InstantiateInterventionTemplateResult('550e8400-e29b-41d4-a716-446655440010', 42);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655440010', $result->interventionId);
    self::assertSame(42, $result->number);
  }

  private static function labelView(): InterventionLabelView
  {
    return new InterventionLabelView(
      '550e8400-e29b-41d4-a716-446655440001',
      '550e8400-e29b-41d4-a716-446655440002',
      'Urgent',
      '#3b82f6',
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }

  private static function templateView(): InterventionTemplateView
  {
    return new InterventionTemplateView(
      '550e8400-e29b-41d4-a716-446655440003',
      '550e8400-e29b-41d4-a716-446655440002',
      'Quarterly audit',
      null,
      'site_setup',
      'normal',
      null,
      null,
      null,
      [],
      [],
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }
  // #endregion
}
