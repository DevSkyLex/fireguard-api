<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices\RevokeAllDevicesCommand;
use TrustedDevice\Presentation\Api\Processor\TrustedDevice\RevokeAllDevicesProcessor;

/**
 * Test RevokeAllDevicesProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeAllDevicesProcessor::class)]
final class RevokeAllDevicesProcessorTest extends TestCase
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

    $processor = new RevokeAllDevicesProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
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
   * Method testProcessDispatchesCommandWhenAuthenticated.
   *
   * Test that process dispatches command when user is authenticated.
   */
  #[Test]
  public function testProcessDispatchesCommandWhenAuthenticated(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (RevokeAllDevicesCommand $command): bool {
        return 'user-123' === $command->userId;
      }));

    $user = $this->createMock(UserInterface::class);
    $user->expects(self::once())
      ->method('getUserIdentifier')
      ->willReturn('user-123');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $processor = new RevokeAllDevicesProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [],
      context: [],
    );
  }
  // #endregion
}
