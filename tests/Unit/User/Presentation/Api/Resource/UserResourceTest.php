<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Resource;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Presentation\Api\Resource\UserResource;

/**
 * Test UserResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserResource::class)]
final class UserResourceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourceCanBeInstantiated(): void
  {
    self::assertInstanceOf(UserResource::class, new UserResource());
  }
  // #endregion
}
