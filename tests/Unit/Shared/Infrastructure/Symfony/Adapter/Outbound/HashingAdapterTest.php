<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\HashedSecret;
use Shared\Infrastructure\Symfony\Adapter\Outbound\HashingAdapter;

use function password_hash;
use function password_verify;

/**
 * Test HashingAdapterTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(HashingAdapter::class)]
final class HashingAdapterTest extends TestCase
{
    // #region Properties
    /**
     * Property adapter.
     *
     * The adapter under test.
     */
    private HashingAdapter $adapter;
    // #endregion

    // #region Setup
    /**
     * Method setUp.
     *
     * Set up the test environment.
     *
     * @return void no return value
     */
    protected function setUp(): void
    {
        $this->adapter = new HashingAdapter();
    }
    // #endregion

    // #region Methods
    /**
     * Method testHashReturnsHashedSecret.
     *
     * Tests that hash returns a HashedSecret.
     *
     * @return void no return value
     */
    #[Test]
    public function testHashReturnsHashedSecret(): void
    {
        $plainValue = 'my_secret_password';

        $result = $this->adapter->hash($plainValue);

        $this->assertInstanceOf(HashedSecret::class, $result);
        $this->assertNotEquals($plainValue, $result->value);
        $this->assertTrue(password_verify($plainValue, $result->value));
    }

    /**
     * Method testVerifyReturnsTrueForMatchingPassword.
     *
     * Tests that verify returns true for matching password.
     *
     * @return void no return value
     */
    #[Test]
    public function testVerifyReturnsTrueForMatchingPassword(): void
    {
        $plainValue = 'my_secret_password';
        $hashedSecret = new HashedSecret(password_hash($plainValue, PASSWORD_BCRYPT));

        $result = $this->adapter->verify($plainValue, $hashedSecret);

        $this->assertTrue($result);
    }

    /**
     * Method testVerifyReturnsFalseForNonMatchingPassword.
     *
     * Tests that verify returns false for non-matching password.
     *
     * @return void no return value
     */
    #[Test]
    public function testVerifyReturnsFalseForNonMatchingPassword(): void
    {
        $plainValue = 'my_secret_password';
        $wrongValue = 'wrong_password';
        $hashedSecret = new HashedSecret(password_hash($plainValue, PASSWORD_BCRYPT));

        $result = $this->adapter->verify($wrongValue, $hashedSecret);

        $this->assertFalse($result);
    }

    /**
     * Method testHashAndVerifyWorkTogether.
     *
     * Tests that hash and verify work together correctly.
     *
     * @return void no return value
     */
    #[Test]
    public function testHashAndVerifyWorkTogether(): void
    {
        $plainValue = 'my_secret_password';

        $hashedSecret = $this->adapter->hash($plainValue);
        $result = $this->adapter->verify($plainValue, $hashedSecret);

        $this->assertTrue($result);
    }
    // #endregion
}
