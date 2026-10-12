<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Query\Totp\GetRecoveryCodesStatus;

use DateTimeImmutable;
use Otp\Application\Port\Outbound\Internal\Totp\TotpEnrollmentRepositoryPort;
use Otp\Application\UseCase\Query\Totp\GetRecoveryCodesStatus\{GetRecoveryCodesStatusHandler, GetRecoveryCodesStatusQuery};
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\TestCase;
use User\Application\Port\Inbound\AccountStatusPort;

use function array_keys;
use function get_object_vars;
use function hash;

/**
 * Class GetRecoveryCodesStatusHandlerTest
 *
 * Verifies safe metadata and inactive account denial without exposing recovery credentials.
 *
 * @category Test
 */
final class GetRecoveryCodesStatusHandlerTest extends TestCase
{
  public function testMissingOrDisabledEnrollmentReportsNoLot(): void
  {
    $disabled = TotpEnrollment::startEnrollment('user', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    foreach ([null, $disabled] as $enrollment) {
      $repo = $this->createStub(TotpEnrollmentRepositoryPort::class);
      $repo->method('findByUserId')->willReturn($enrollment);
      $result = new GetRecoveryCodesStatusHandler($repo, $this->accounts(true))(new GetRecoveryCodesStatusQuery('user'));
      self::assertSame(0, $result->remainingCount);
      self::assertNull($result->generatedAt);
    }
  }

  public function testActiveLotReportsCountAndGenerationTimeOnly(): void
  {
    $time = new DateTimeImmutable();
    $enrollment = TotpEnrollment::startEnrollment('user', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);
    $enrollment->replaceRecoveryCodes([hash('sha256', 'test-proof')], $time);
    $repo = $this->createStub(TotpEnrollmentRepositoryPort::class);
    $repo->method('findByUserId')->willReturn($enrollment);
    $result = new GetRecoveryCodesStatusHandler($repo, $this->accounts(true))(new GetRecoveryCodesStatusQuery('user'));
    self::assertSame(1, $result->remainingCount);
    self::assertSame($time, $result->generatedAt);
    self::assertSame(['remainingCount', 'generatedAt'], array_keys(get_object_vars($result)));
  }

  public function testInactiveAccountDeniesBeforeReadingEnrollment(): void
  {
    $repo = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repo->expects(self::never())->method('findByUserId');
    $this->expectException(TotpEnrollmentUnavailableException::class);
    new GetRecoveryCodesStatusHandler($repo, $this->accounts(false))(new GetRecoveryCodesStatusQuery('user'));
  }

  private function accounts(bool $active): AccountStatusPort
  {
    $accounts = $this->createStub(AccountStatusPort::class);
    $accounts->method('isActive')->willReturn($active);

    return $accounts;
  }
}
