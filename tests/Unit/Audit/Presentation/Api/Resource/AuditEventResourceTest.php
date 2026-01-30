<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Presentation\Api\Resource;

use Audit\Presentation\Api\Resource\AuditEventResource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuditEventResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuditEventResource::class)]
final class AuditEventResourceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourceCanBeInstantiated(): void
  {
    self::assertInstanceOf(AuditEventResource::class, new AuditEventResource());
  }
  // #endregion
}
