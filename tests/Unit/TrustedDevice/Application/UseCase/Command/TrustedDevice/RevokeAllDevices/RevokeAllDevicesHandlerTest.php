<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices\{RevokeAllDevicesCommand, RevokeAllDevicesHandler, RevokeAllDevicesResult};

/**
 * Test RevokeAllDevicesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeAllDevicesHandler::class)]
final class RevokeAllDevicesHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeRevokesAllDevices.
   *
   * Test that __invoke revokes all devices
   * for the user and returns the count.
   */
  #[Test]
  public function testInvokeRevokesAllDevices(): void
  {
    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('revokeAllForUser')
      ->with('user-123')
      ->willReturn(3);

    $handler = new RevokeAllDevicesHandler(repository: $repository);
    $command = new RevokeAllDevicesCommand(userId: 'user-123');

    $result = $handler->__invoke(command: $command);

    self::assertInstanceOf(RevokeAllDevicesResult::class, $result);
    self::assertSame(3, $result->revokedCount);
  }
  // #endregion
}
