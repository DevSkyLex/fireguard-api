<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Contract\Execution;

use Approval\Application\Contract\Execution\DeferredActionContext;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DeferredActionContext.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeferredActionContext::class)]
final class DeferredActionContextTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $context = new DeferredActionContext(
      organizationId: 'org-1',
      actionType: 'equipment_decommission',
      subjectId: 'equip-1',
      payload: ['equipmentId' => 'equip-1'],
    );

    self::assertSame('org-1', $context->organizationId);
    self::assertSame('equipment_decommission', $context->actionType);
    self::assertSame('equip-1', $context->subjectId);
    self::assertSame(['equipmentId' => 'equip-1'], $context->payload);
  }
}
