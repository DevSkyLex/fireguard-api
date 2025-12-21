<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\SetupTotp;

use Otp\Application\Port\Outbound\TotpServicePort;
use Otp\Application\UseCase\Command\SetupTotp\SetupTotpCommand;
use Otp\Application\UseCase\Command\SetupTotp\SetupTotpHandler;
use Otp\Application\UseCase\Command\SetupTotp\SetupTotpResult;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test SetupTotpHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetupTotpHandler::class)]
final class SetupTotpHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeGeneratesTotpSecret(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $qrCodeUri = 'otpauth://totp/FireGuard%20Auth:test@example.com?secret=JBSWY3DPEHPK3PXP&issuer=FireGuard%20Auth';

    $totpService = $this->createMock(TotpServicePort::class);
    $totpService->expects(self::once())
      ->method('generateSecret')
      ->willReturn($secret);
    $totpService->expects(self::once())
      ->method('getProvisioningUri')
      ->with($secret, 'test@example.com', 'FireGuard Auth')
      ->willReturn($qrCodeUri);

    $handler = new SetupTotpHandler(totpService: $totpService);

    $command = new SetupTotpCommand(
      userId: 'user-123',
      accountName: 'test@example.com',
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(SetupTotpResult::class, $result);
    self::assertEquals('JBSWY3DPEHPK3PXP', $result->secret);
    self::assertEquals($qrCodeUri, $result->qrCodeUri);
  }
}
