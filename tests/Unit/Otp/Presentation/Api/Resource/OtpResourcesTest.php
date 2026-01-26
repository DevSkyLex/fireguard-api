<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Presentation\Api\Resource;

use Otp\Presentation\Api\Resource\{ChallengeResource, ConfigResource, TotpResource};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpResourcesTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ChallengeResource::class)]
#[CoversClass(className: ConfigResource::class)]
#[CoversClass(className: TotpResource::class)]
final class OtpResourcesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourcesCanBeInstantiated(): void
  {
    self::assertInstanceOf(ChallengeResource::class, new ChallengeResource());
    self::assertInstanceOf(ConfigResource::class, new ConfigResource());
    self::assertInstanceOf(TotpResource::class, new TotpResource());
  }
  // #endregion
}
