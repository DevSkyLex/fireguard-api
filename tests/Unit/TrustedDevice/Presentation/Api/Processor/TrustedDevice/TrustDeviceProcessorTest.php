<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Post;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice\{TrustDeviceCommand, TrustDeviceResult};
use TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice\TrustDeviceOutput;
use TrustedDevice\Presentation\Api\EventSubscriber\TrustedDeviceCookieListener;
use TrustedDevice\Presentation\Api\Processor\TrustedDevice\TrustDeviceProcessor;
use TrustedDevice\Presentation\Api\Service\TrustedDeviceCookieService;

/**
 * Test TrustDeviceProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustDeviceProcessor::class)]
final class TrustDeviceProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessCreatesCookieAndOutput.
   *
   * Test that process dispatches command and sets cookie on request.
   */
  #[Test]
  public function testProcessCreatesCookieAndOutput(): void
  {
    $request = new Request();
    $request->headers->set('User-Agent', 'Mozilla/5.0');
    $request->headers->set('Accept-Language', 'en-US');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $user = $this->createMock(UserInterface::class);
    $user->expects(self::once())
      ->method('getUserIdentifier')
      ->willReturn('user-123');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $expiresAt = new DateTimeImmutable('+1 day');
    $commandResult = new TrustDeviceResult(
      deviceId: 'device-123',
      token: 'token-abc',
      deviceName: 'Chrome on Windows',
      expiresAt: $expiresAt,
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (TrustDeviceCommand $command): bool {
        return 'user-123' === $command->userId
          && 'Mozilla/5.0' === $command->userAgent
          && '127.0.0.1' === $command->ipAddress
          && 'en-US' === $command->acceptLanguage;
      }))
      ->willReturn($commandResult);

    $cookieService = new TrustedDeviceCookieService(
      environment: 'test',
      cookieBaseName: 'trusted_device',
      lifetime: 3600,
    );

    $processor = new TrustDeviceProcessor(
      commandBus: $commandBus,
      security: $security,
      requestStack: $requestStack,
      cookieService: $cookieService,
    );

    $output = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [],
      context: [],
    );

    self::assertInstanceOf(TrustDeviceOutput::class, $output);
    self::assertSame('device-123', $output->deviceId);
    self::assertSame('token-abc', $output->token);
    self::assertSame('Chrome on Windows', $output->deviceName);
    self::assertSame($expiresAt, $output->expiresAt);

    $cookie = $request->attributes->get(TrustedDeviceCookieListener::REQUEST_ATTRIBUTE);
    self::assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $cookie);
    self::assertSame('trusted_device', $cookie->getName());
    self::assertSame('token-abc', $cookie->getValue());
  }

  /**
   * Method testProcessThrowsBadRequestWhenUserMissing.
   *
   * Test that process throws when no authenticated user.
   */
  #[Test]
  public function testProcessThrowsBadRequestWhenUserMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new TrustDeviceProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
      requestStack: $requestStack,
      cookieService: $this->createMock(TrustedDeviceCookieService::class),
    );

    $this->expectException(BadRequestHttpException::class);
    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [],
      context: [],
    );
  }

  /**
   * Method testProcessThrowsBadRequestWhenRequestMissing.
   *
   * Test that process throws when request is missing.
   */
  #[Test]
  public function testProcessThrowsBadRequestWhenRequestMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createMock(UserInterface::class));

    $processor = new TrustDeviceProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
      requestStack: new RequestStack(),
      cookieService: $this->createMock(TrustedDeviceCookieService::class),
    );

    $this->expectException(BadRequestHttpException::class);
    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [],
      context: [],
    );
  }
  // #endregion
}
