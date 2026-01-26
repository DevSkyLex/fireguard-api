<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Resource;

use Auth\Presentation\Api\Resource\AuthResource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuthResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuthResource::class)]
final class AuthResourceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourceCanBeInstantiated(): void
  {
    self::assertInstanceOf(AuthResource::class, new AuthResource());
  }
  // #endregion
}
