<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Domain\ValueObject\SessionMetadata;

/**
 * Class SessionMetadataTest.
 *
 * Unit tests for the SessionMetadata Value Object.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SessionMetadata::class)]
final class SessionMetadataTest extends TestCase
{
  // #region Methods
  /**
   * Method testDefaultValues.
   *
   * Tests that default values are correctly set.
   */
  #[Test]
  public function testDefaultValues(): void
  {
    $metadata = new SessionMetadata();

    $this->assertNull(actual: $metadata->deviceType);
    $this->assertNull(actual: $metadata->browser);
    $this->assertNull(actual: $metadata->operatingSystem);
    $this->assertNull(actual: $metadata->country);
    $this->assertNull(actual: $metadata->city);
    $this->assertFalse(condition: $metadata->rememberMe);
  }

  /**
   * Method testCustomValues.
   *
   * Tests that custom values can be set.
   */
  #[Test]
  public function testCustomValues(): void
  {
    $metadata = new SessionMetadata(
      deviceType: 'desktop',
      browser: 'Chrome',
      operatingSystem: 'Windows 11',
      country: 'FR',
      city: 'Paris',
      rememberMe: true,
    );

    $this->assertEquals(expected: 'desktop', actual: $metadata->deviceType);
    $this->assertEquals(expected: 'Chrome', actual: $metadata->browser);
    $this->assertEquals(expected: 'Windows 11', actual: $metadata->operatingSystem);
    $this->assertEquals(expected: 'FR', actual: $metadata->country);
    $this->assertEquals(expected: 'Paris', actual: $metadata->city);
    $this->assertTrue(condition: $metadata->rememberMe);
  }

  /**
   * Method testToArray.
   *
   * Tests the toArray method.
   */
  #[Test]
  public function testToArray(): void
  {
    $metadata = new SessionMetadata(
      deviceType: 'mobile',
      browser: 'Safari',
      operatingSystem: 'iOS 17',
      country: 'US',
      city: 'New York',
      rememberMe: false,
    );

    $array = $metadata->toArray();

    $this->assertEquals(expected: [
      'device_type' => 'mobile',
      'browser' => 'Safari',
      'operating_system' => 'iOS 17',
      'country' => 'US',
      'city' => 'New York',
      'remember_me' => false,
    ], actual: $array);
  }

  /**
   * Method testFromArray.
   *
   * Tests the fromArray factory method.
   */
  #[Test]
  public function testFromArray(): void
  {
    $data = [
      'device_type' => 'tablet',
      'browser' => 'Firefox',
      'operating_system' => 'Android 14',
      'country' => 'DE',
      'city' => 'Berlin',
      'remember_me' => true,
    ];

    $metadata = SessionMetadata::fromArray($data);

    $this->assertEquals(expected: 'tablet', actual: $metadata->deviceType);
    $this->assertEquals(expected: 'Firefox', actual: $metadata->browser);
    $this->assertEquals(expected: 'Android 14', actual: $metadata->operatingSystem);
    $this->assertEquals(expected: 'DE', actual: $metadata->country);
    $this->assertEquals(expected: 'Berlin', actual: $metadata->city);
    $this->assertTrue(condition: $metadata->rememberMe);
  }

  /**
   * Method testFromArrayWithEmptyData.
   *
   * Tests fromArray with empty data.
   */
  #[Test]
  public function testFromArrayWithEmptyData(): void
  {
    $metadata = SessionMetadata::fromArray([]);

    $this->assertNull(actual: $metadata->deviceType);
    $this->assertNull(actual: $metadata->browser);
    $this->assertFalse(condition: $metadata->rememberMe);
  }
  // #endregion
}
