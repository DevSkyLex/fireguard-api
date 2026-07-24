<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Subject;

use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingSubjectResolution.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingSubjectResolution::class)]
final class MessagingSubjectResolutionTest extends TestCase
{
  #[Test]
  public function itRoundTripsAResolvedSubject(): void
  {
    $resolution = new MessagingSubjectResolution(true, 'Main facility', 'organization.facility.read');

    self::assertTrue($resolution->exists);
    self::assertSame('Main facility', $resolution->label);
    self::assertSame('organization.facility.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function itRoundTripsAMissingSubjectWithNoLabel(): void
  {
    $resolution = new MessagingSubjectResolution(false, null, 'organization.equipment.read');

    self::assertFalse($resolution->exists);
    self::assertNull($resolution->label);
    self::assertSame('organization.equipment.read', $resolution->requiredReadPermission);
  }
}
