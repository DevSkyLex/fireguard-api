<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Delete;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Security\Core\User\UserInterface;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\RevokeDeviceCommand;
use TrustedDevice\Domain\Exception\TrustedDeviceNotFoundException;
use TrustedDevice\Presentation\Api\Processor\TrustedDevice\RevokeDeviceProcessor;

/**
 * Test RevokeDeviceProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeDeviceProcessor::class)]
final class RevokeDeviceProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessThrowsBadRequestWhenUserMissing.
   *
   * Test that process throws when user is missing.
   */
  #[Test]
  public function testProcessThrowsBadRequestWhenUserMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new RevokeDeviceProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['id' => 'device-123'],
      context: [],
    );
  }

  /**
   * Method testProcessThrowsBadRequestWhenIdMissing.
   *
   * Test that process throws when device ID is missing.
   */
  #[Test]
  public function testProcessThrowsBadRequestWhenIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createStub(UserInterface::class));

    $processor = new RevokeDeviceProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: [],
      context: [],
    );
  }

  /**
   * Method testProcessThrowsNotFoundWhenDeviceMissing.
   *
   * Test that process throws when device not found.
   */
  #[Test]
  public function testProcessThrowsNotFoundWhenDeviceMissing(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(TrustedDeviceNotFoundException::create(id: 'device-123'));

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createUserMock('user-123'));

    $processor = new RevokeDeviceProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);
    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['id' => 'device-123'],
      context: [],
    );
  }

  /**
   * Method testProcessUnwrapsNotFoundFromPreviousException.
   *
   * Test that process unwraps not found from previous exception.
   */
  #[Test]
  public function testProcessUnwrapsNotFoundFromPreviousException(): void
  {
    $previous = TrustedDeviceNotFoundException::create(id: 'device-456');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('wrapped', 0, $previous));

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createUserMock('user-123'));

    $processor = new RevokeDeviceProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);
    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['id' => 'device-456'],
      context: [],
    );
  }

  /**
   * Method testProcessDispatchesCommandWhenValid.
   *
   * Test that process dispatches the revoke command.
   */
  #[Test]
  public function testProcessDispatchesCommandWhenValid(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (RevokeDeviceCommand $command): bool {
        return 'device-789' === $command->deviceId
          && 'user-789' === $command->userId;
      }));

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createUserMock('user-789'));

    $processor = new RevokeDeviceProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['id' => 'device-789'],
      context: [],
    );
  }

  /**
   * Method testProcessRethrowsUnrelatedException.
   *
   * Test that process rethrows an exception chain without a not-found cause.
   */
  #[Test]
  public function testProcessRethrowsUnrelatedException(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom'));

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createUserMock('user-321'));

    $processor = new RevokeDeviceProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('boom');

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['id' => 'device-321'],
      context: [],
    );
  }

  private function createUserMock(string $userId): UserInterface
  {
    $user = $this->createMock(UserInterface::class);
    $user->expects(self::once())
      ->method('getUserIdentifier')
      ->willReturn($userId);

    return $user;
  }
  // #endregion
}
