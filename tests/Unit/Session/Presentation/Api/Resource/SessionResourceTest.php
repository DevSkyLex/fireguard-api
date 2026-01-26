<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Presentation\Api\Resource;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Presentation\Api\Resource\SessionResource;

/**
 * Test SessionResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SessionResource::class)]
final class SessionResourceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourceCanBeInstantiated(): void
  {
    self::assertInstanceOf(SessionResource::class, new SessionResource());
  }
  // #endregion
}
