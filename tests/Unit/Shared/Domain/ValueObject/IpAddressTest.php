<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\IpAddress;

/**
 * Class IpAddressTest.
 *
 * Unit tests for the IpAddress Value Object.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\ValueObject\IpAddress
 */
#[CoversClass(className: IpAddress::class)]
final class IpAddressTest extends TestCase
{
    // #region Methods
    /**
     * Method testCanBeCreatedWithValidIpv4.
     *
     * Tests that a valid IPv4 address can be created.
     *
     * @return void no return value
     */
    #[Test]
    public function testCanBeCreatedWithValidIpv4(): void
    {
        $value = '192.168.1.1';
        $ip = new IpAddress(value: $value);

        $this->assertEquals(expected: $value, actual: $ip->value);
        $this->assertEquals(expected: $value, actual: (string) $ip);
    }

    /**
     * Method testCanBeCreatedWithValidIpv6.
     *
     * Tests that a valid IPv6 address can be created.
     *
     * @return void no return value
     */
    #[Test]
    public function testCanBeCreatedWithValidIpv6(): void
    {
        $value = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $ip = new IpAddress(value: $value);

        $this->assertEquals(expected: $value, actual: $ip->value);
        $this->assertEquals(expected: $value, actual: (string) $ip);
    }

    /**
     * Method testCannotBeCreatedWithInvalidValue.
     *
     * Tests that creating an IpAddress with an
     * invalid value throws an exception.
     *
     * @return void no return value
     */
    #[Test]
    public function testCannotBeCreatedWithInvalidValue(): void
    {
        $this->expectException(exception: InvalidValueException::class);
        new IpAddress(value: 'invalid-ip');
    }

    /**
     * Method testEquality.
     *
     * Tests equality comparison between
     * IpAddress objects.
     *
     * @return void no return value
     */
    #[Test]
    public function testEquality(): void
    {
        $ip1 = new IpAddress(value: '192.168.1.1');
        $ip2 = new IpAddress(value: '192.168.1.1');
        $ip3 = new IpAddress(value: '10.0.0.1');

        $this->assertTrue(condition: $ip1->equals($ip2));
        $this->assertFalse(condition: $ip1->equals($ip3));
    }
    // #endregion
}
