<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Adapter\Auth;

use Otp\Application\Port\Inbound\Totp\TotpStatusPort;
use Otp\Infrastructure\Adapter\Auth\TotpEnrollmentCheckAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test TotpEnrollmentCheckAdapterTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpEnrollmentCheckAdapter::class)]
final class TotpEnrollmentCheckAdapterTest extends TestCase
{
  #[Test]
  public function testIsEnrolledDelegatesToTotpStatusPort(): void
  {
    /** @var TotpStatusPort&MockObject $totpStatus */
    $totpStatus = $this->createMock(TotpStatusPort::class);
    $totpStatus->expects(self::once())
      ->method('isEnabled')
      ->with('user-1')
      ->willReturn(true);

    $adapter = new TotpEnrollmentCheckAdapter(totpStatus: $totpStatus);

    self::assertTrue($adapter->isEnrolled('user-1'));
  }

  #[Test]
  public function testIsEnrolledReturnsFalseWhenPortThrows(): void
  {
    /** @var TotpStatusPort&MockObject $totpStatus */
    $totpStatus = $this->createMock(TotpStatusPort::class);
    $totpStatus->expects(self::once())
      ->method('isEnabled')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new TotpEnrollmentCheckAdapter(totpStatus: $totpStatus);

    self::assertFalse($adapter->isEnrolled('user-1'));
  }
}
