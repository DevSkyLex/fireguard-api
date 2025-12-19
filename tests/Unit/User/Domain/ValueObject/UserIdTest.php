<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Domain\ValueObject\UserId;

/**
 * Test UserIdTest.
 *
 * @category ValueObject Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserId::class)]
final class UserIdTest extends TestCase
{
    // #region Methods
    /**
     * Method testCanBeCreatedWithValidUuid.
     *
     * Tests that a UserId can be created with a valid UUID.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testCanBeCreatedWithValidUuid(): void
    {
        $id = new UserId('550e8400-e29b-41d4-a716-446655440000');
        $this->assertInstanceOf(UserId::class, $id);
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $id->value);
    }
    // #endregion
}
