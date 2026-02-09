<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Resource;

use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use User\Presentation\Api\Resource\UserResource;

/**
 * Test UserResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversNothing]
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
