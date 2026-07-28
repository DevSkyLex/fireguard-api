<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Factory\InterventionChangeOutputFactory;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionChangeOutputFactoryTest.
 *
 * The workflow gateway hands back an untyped array, so the factory is the last
 * place a malformed payload can be caught before it reaches the API contract —
 * a missing or mistyped key must fail loudly rather than coerce.
 *
 * @category Factory Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionChangeOutputFactory::class)]
final class InterventionChangeOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string CHANGE_ID = '550e8400-e29b-41d4-a716-446655484001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655484002';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromViewProjectsTheWorkflowPayload(): void
  {
    $output = new InterventionChangeOutputFactory()->fromView($this->view([
      'workItem' => '/api/intervention-work-items/wi-1',
    ]));

    self::assertSame(self::CHANGE_ID, $output->id);
    self::assertSame('/api/interventions/int-1', $output->intervention);
    self::assertSame('/api/intervention-work-items/wi-1', $output->workItem);
    self::assertSame('work_item', $output->resource);
    self::assertSame(['title' => 'Replace extinguisher'], $output->patch);
    self::assertSame('proposed', $output->status);
    self::assertSame(3, $output->revision);
    self::assertSame('2026-07-01T08:00:00+00:00', $output->createdAt);
    self::assertSame('2026-07-02T08:00:00+00:00', $output->updatedAt);
  }

  #[Test]
  public function testFromViewKeepsAnInterventionLevelChangeWithoutAWorkItem(): void
  {
    $output = new InterventionChangeOutputFactory()->fromView($this->view());

    self::assertNull($output->workItem);
  }

  #[Test]
  public function testFromViewRejectsAPayloadWhoseRevisionIsNotAnInteger(): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('revision must be an integer.');

    new InterventionChangeOutputFactory()->fromView($this->view(['revision' => '3']));
  }

  /**
   * @param array<string, mixed> $overrides the payload overrides
   */
  private function view(array $overrides = []): InterventionWorkflowView
  {
    return new InterventionWorkflowView(
      resource: 'intervention_change',
      organizationId: self::ORGANIZATION_ID,
      data: [
        'id' => self::CHANGE_ID,
        'intervention' => '/api/interventions/int-1',
        'workItem' => null,
        'resource' => 'work_item',
        'patch' => ['title' => 'Replace extinguisher'],
        'status' => 'proposed',
        'revision' => 3,
        'createdAt' => '2026-07-01T08:00:00+00:00',
        'updatedAt' => '2026-07-02T08:00:00+00:00',
        ...$overrides,
      ],
    );
  }
  // #endregion
}
