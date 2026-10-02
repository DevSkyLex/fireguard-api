<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Challenge\GenerateOtp;

use Otp\Application\Contract\Challenge\{OtpChannel as ContractOtpChannel, OtpPurpose as ContractOtpPurpose};
use Otp\Application\Port\Outbound\Challenge\{OtpNotifierPort, OtpRepositoryPort};
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpHandler, GenerateOtpResult};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Http\RequestOrigin;
use Shared\Application\Contract\Notification\EmailRequestDetails;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{GeoIpLookupPort, RequestOriginPort};

/**
 * Test GenerateOtpHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateOtpHandler::class)]
final class GenerateOtpHandlerTest extends TestCase
{
  #[Test]
  public function testOriginFailureStillDeliversExplicitEmptyContext(): void
  {
    $origin = $this->createMock(RequestOriginPort::class);
    $origin->expects(self::once())->method('current')->willThrowException(new RuntimeException('context unavailable'));
    $geo = $this->createMock(GeoIpLookupPort::class);
    $geo->expects(self::never())->method('locate');
    $this->assertDeliveryAfterEnrichment($origin, $geo, new EmailRequestDetails());
  }

  #[Test]
  public function testGeoIpFailurePreservesBrowserAndLocaleAndDelivers(): void
  {
    $origin = $this->createStub(RequestOriginPort::class);
    $origin->method('current')->willReturn(new RequestOrigin('8.8.8.8', 'Firefox', 'Linux', 'fr'));
    $geo = $this->createMock(GeoIpLookupPort::class);
    $geo->expects(self::once())->method('locate')->with('8.8.8.8')->willThrowException(new RuntimeException('lookup unavailable'));
    $this->assertDeliveryAfterEnrichment($origin, $geo, new EmailRequestDetails('Firefox', 'Linux', locale: 'fr'));
  }

  #[Test]
  public function testDeliveryFailureIsStillObservable(): void
  {
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(new OtpId('123e4567-e89b-12d3-a456-426614174001'));
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())->method('revokeAllForUser');
    $repository->expects(self::once())->method('save');
    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::once())->method('send')->willThrowException(new RuntimeException('delivery failed'));
    $handler = new GenerateOtpHandler($repository, $notifier, $uuid);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('delivery failed');
    $handler(new GenerateOtpCommand('user-123', ContractOtpPurpose::LOGIN, ContractOtpChannel::EMAIL, 'test@example.com'));
  }

  #[Test]
  public function testEmailUsesCurrentRequestOnEveryGenerationEvenWithoutRecognizedBrowser(): void
  {
    $repository = $this->createStub(OtpRepositoryPort::class);
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(new OtpId('123e4567-e89b-12d3-a456-426614174001'));
    $origin = $this->createMock(RequestOriginPort::class);
    $origin->expects(self::exactly(2))->method('current')->willReturnOnConsecutiveCalls(
      new RequestOrigin('8.8.8.8'),
      new RequestOrigin('9.9.9.9'),
    );
    $geo = $this->createMock(GeoIpLookupPort::class);
    $geo->expects(self::exactly(2))->method('locate')->willReturnCallback(
      static fn (string $ip): \Shared\Application\Contract\GeoIp\IpLocation => new \Shared\Application\Contract\GeoIp\IpLocation('8.8.8.8' === $ip ? 'FR' : 'ES'),
    );
    $details = [];
    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::exactly(2))->method('send')->willReturnCallback(
      static function (Otp $otp, ?EmailRequestDetails $context) use (&$details): void {
        $details[] = $context;
      },
    );
    $handler = new GenerateOtpHandler($repository, $notifier, $uuid, geoIp: $geo, requestOrigin: $origin);
    $command = new GenerateOtpCommand('user-123', ContractOtpPurpose::LOGIN, ContractOtpChannel::EMAIL, 'test@example.com');
    $handler($command);
    $handler($command);
    self::assertSame('FR', $details[0]?->location?->countryCode);
    self::assertSame('ES', $details[1]?->location?->countryCode);
    self::assertNotNull($details[0]);
    self::assertNull($details[0]->browser);
  }

  #[Test]
  public function testSmsAndNonHttpEmailDoNotPerformLookups(): void
  {
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(new OtpId('123e4567-e89b-12d3-a456-426614174001'));
    $origin = $this->createStub(RequestOriginPort::class);
    $origin->method('current')->willReturn(null);
    $geo = $this->createMock(GeoIpLookupPort::class);
    $geo->expects(self::never())->method('locate');
    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::exactly(2))->method('send');
    $handler = new GenerateOtpHandler($this->createStub(OtpRepositoryPort::class), $notifier, $uuid, geoIp: $geo, requestOrigin: $origin);
    $handler(new GenerateOtpCommand('user-123', ContractOtpPurpose::LOGIN, ContractOtpChannel::SMS, '+33612345678'));
    $handler(new GenerateOtpCommand('user-123', ContractOtpPurpose::LOGIN, ContractOtpChannel::EMAIL, 'test@example.com'));
  }

  #[Test]
  public function testInvokeGeneratesOtpAndSendsNotification(): void
  {
    $otpId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('revokeAllForUser')
      ->with('user-123', OtpPurpose::LOGIN);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Otp::class));

    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::once())
      ->method('send')
      ->with(self::isInstanceOf(Otp::class));

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OtpId::class)
      ->willReturn(new OtpId($otpId));

    $handler = new GenerateOtpHandler(
      otpRepository: $repository,
      otpNotifier: $notifier,
      uuidFactory: $uuidFactory,
    );

    $command = new GenerateOtpCommand(
      userId: 'user-123',
      purpose: ContractOtpPurpose::LOGIN,
      channel: ContractOtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(GenerateOtpResult::class, $result);
    self::assertEquals($otpId, $result->otpId);
    self::assertStringContainsString('@example.com', $result->maskedRecipient);
  }

  #[Test]
  public function testInvokeDoesNotSendNotificationForTotp(): void
  {
    $otpId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createStub(OtpRepositoryPort::class);

    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::never())->method('send');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->willReturn(new OtpId($otpId));

    $handler = new GenerateOtpHandler(
      otpRepository: $repository,
      otpNotifier: $notifier,
      uuidFactory: $uuidFactory,
    );

    $command = new GenerateOtpCommand(
      userId: 'user-123',
      purpose: ContractOtpPurpose::LOGIN,
      channel: ContractOtpChannel::TOTP,
      recipient: 'authenticator',
    );

    $result = $handler->__invoke($command);

    self::assertEquals('Authenticator App', $result->maskedRecipient);
  }

  private function assertDeliveryAfterEnrichment(RequestOriginPort $origin, GeoIpLookupPort $geo, EmailRequestDetails $expected): void
  {
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(new OtpId('123e4567-e89b-12d3-a456-426614174001'));
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())->method('revokeAllForUser');
    $saved = false;
    $repository->expects(self::once())->method('save')->willReturnCallback(static function () use (&$saved): void {
      $saved = true;
    });
    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::once())->method('send')->willReturnCallback(static function (Otp $otp, ?EmailRequestDetails $details) use (&$saved, $expected): void {
      self::assertTrue($saved);
      self::assertEquals($expected, $details);
    });
    $handler = new GenerateOtpHandler($repository, $notifier, $uuid, geoIp: $geo, requestOrigin: $origin);
    $result = $handler(new GenerateOtpCommand('user-123', ContractOtpPurpose::LOGIN, ContractOtpChannel::EMAIL, 'test@example.com'));
    self::assertSame('123e4567-e89b-12d3-a456-426614174001', $result->otpId);
  }
}
