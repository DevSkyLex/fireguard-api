<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Domain\ValueObject\HashedPassword;

/**
 * Test HashedPasswordTest.
 *
 * @category ValueObject Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(HashedPassword::class)]
final class HashedPasswordTest extends TestCase
{
    // #region Methods
    /**
     * Method testVerifiesCorrectly.
     *
     * Tests that hashed password verifies
     * correctly.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testVerifiesCorrectly(): void
    {
        $password = HashedPassword::fromPlain('secret');
        $this->assertTrue($password->verify('secret'));
        $this->assertFalse($password->verify('wrong'));
    }
    // #endregion
}
