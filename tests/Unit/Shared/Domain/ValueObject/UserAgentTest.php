<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\UserAgent;

/**
 * Test UserAgentTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\ValueObject\UserAgent
 */
#[CoversClass(className: UserAgent::class)]
final class UserAgentTest extends TestCase
{
    /**
     * Method testCanBeCreatedWithValidValue.
     *
     * Tests that a valid UserAgent can be created.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testCanBeCreatedWithValidValue(): void
    {
        $value = 'Mozilla/5.0';
        $userAgent = new UserAgent($value);

        $this->assertEquals($value, $userAgent->value);
        $this->assertEquals($value, (string) $userAgent);
    }

    /**
     * Method testCannotBeCreatedWithEmptyValue.
     *
     * Tests that creating a UserAgent with an empty value throws an exception.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testCannotBeCreatedWithEmptyValue(): void
    {
        $this->expectException(InvalidValueException::class);
        new UserAgent('');
    }

    /**
     * Method testEquality.
     *
     * Tests equality comparison between UserAgent objects.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testEquality(): void
    {
        $ua1 = new UserAgent('Mozilla/5.0');
        $ua2 = new UserAgent('Mozilla/5.0');
        $ua3 = new UserAgent('Chrome/1.0');

        $this->assertTrue($ua1->equals($ua2));
        $this->assertFalse($ua1->equals($ua3));
    }

    /**
     * Method testIsMobile.
     *
     * Tests the isMobile method to detect mobile devices.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testIsMobile(): void
    {
        $mobile = new UserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)');
        $desktop = new UserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

        $this->assertTrue($mobile->isMobile());
        $this->assertFalse($desktop->isMobile());
    }

    /**
     * Method testGetBrowser.
     *
     * Tests the getBrowser method to extract browser name from User-Agent.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testGetBrowser(): void
    {
        $chrome = new UserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        $firefox = new UserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0');
        $unknown = new UserAgent('Unknown Browser');

        $this->assertEquals('Chrome', $chrome->getBrowser());
        $this->assertEquals('Firefox', $firefox->getBrowser());
        $this->assertNull($unknown->getBrowser());
    }
}
