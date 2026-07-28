<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Contract\Workflow;

use Intervention\Application\Contract\Workflow\InterventionWorkflowMutation;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionWorkflowMutationTest.
 *
 * @category Contract Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionWorkflowMutation::class)]
final class InterventionWorkflowMutationTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorAssignsEveryProperty(): void
  {
    $mutation = new InterventionWorkflowMutation(
      resource: 'intervention',
      action: 'update',
      userId: '550e8400-e29b-41d4-a716-446655440000',
      id: '550e8400-e29b-41d4-a716-446655440001',
      payload: ['name' => 'Quarterly audit'],
      expectedRevision: 4,
      createOnly: true,
    );

    self::assertSame('intervention', $mutation->resource);
    self::assertSame('update', $mutation->action);
    self::assertSame('550e8400-e29b-41d4-a716-446655440000', $mutation->userId);
    self::assertSame('550e8400-e29b-41d4-a716-446655440001', $mutation->id);
    self::assertSame(['name' => 'Quarterly audit'], $mutation->payload);
    self::assertSame(4, $mutation->expectedRevision);
    self::assertTrue($mutation->createOnly);
  }

  #[Test]
  public function testOptionalArgumentsFallBackToTheirDefaults(): void
  {
    $mutation = new InterventionWorkflowMutation(
      resource: 'work_item',
      action: 'create',
      userId: '550e8400-e29b-41d4-a716-446655440000',
      id: null,
      payload: [],
    );

    self::assertNull($mutation->id);
    self::assertSame([], $mutation->payload);
    self::assertNull($mutation->expectedRevision);
    self::assertFalse($mutation->createOnly);
  }
  // #endregion
}
