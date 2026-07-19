<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Service;

use Otp\Application\Port\Outbound\Totp\TotpEnrollmentRepositoryPort;
use Otp\Application\Service\TotpStatusService;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test TotpStatusServiceTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpStatusService::class)]
final class TotpStatusServiceTest extends TestCase
{
  #[Test]
  public function testIsEnabledReturnsTrueForActiveEnrollment(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 5,
    );
    $enrollment->confirmPending(true);

    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with('user-1')
      ->willReturn($enrollment);

    $service = new TotpStatusService(enrollmentRepository: $repository);

    self::assertTrue($service->isEnabled('user-1'));
  }

  #[Test]
  public function testIsEnabledReturnsFalseWhenNoEnrollment(): void
  {
    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->willReturn(null);

    $service = new TotpStatusService(enrollmentRepository: $repository);

    self::assertFalse($service->isEnabled('user-1'));
  }

  #[Test]
  public function testIsEnabledReturnsFalseWhenOnlyPending(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 5,
    );

    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->willReturn($enrollment);

    $service = new TotpStatusService(enrollmentRepository: $repository);

    self::assertFalse($service->isEnabled('user-1'));
  }
}
