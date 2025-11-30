<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Symfony\Security;

use Auth\Infrastructure\Symfony\Security\SecurityUser;
use Auth\Infrastructure\Symfony\Security\SecurityUserProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use User\Application\UseCase\Query\GetUser\GetUserResult;

/**
 * Class SecurityUserProviderTest
 *
 * Unit tests for the SecurityUserProvider.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Infrastructure\Symfony\Security
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SecurityUserProvider::class)]
final class SecurityUserProviderTest extends TestCase
{
  //#region Properties
  /**
   * Property queryBus
   *
   * Mocked query bus.
   *
   * @access private
   *
   * @var QueryBusPort&MockObject
   */
  private QueryBusPort&MockObject $queryBus;

  /**
   * Property provider
   *
   * SecurityUserProvider instance.
   *
   * @access private
   *
   * @var SecurityUserProvider
   */
  private SecurityUserProvider $provider;
  //#endregion

  //#region Methods
  /**
   * Method setUp
   *
   * Sets up the test environment.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    $this->queryBus = $this->createMock(QueryBusPort::class);
    $this->provider = new SecurityUserProvider($this->queryBus);
  }

  /**
   * Method testLoadUserByIdThrowsExceptionWhenUserNotFound
   *
   * Tests that loadUserById throws exception when user not found.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLoadUserByIdThrowsExceptionWhenUserNotFound(): void
  {
    $userId = 'non-existent-user';

    $this->queryBus
      ->expects($this->once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: null));

    $this->expectException(UserNotFoundException::class);

    $this->provider->loadUserById($userId);
  }

  /**
   * Method testRefreshUserThrowsExceptionForUnsupportedUser
   *
   * Tests that refreshUser throws exception for unsupported user.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRefreshUserThrowsExceptionForUnsupportedUser(): void
  {
    $unsupportedUser = $this->createMock(UserInterface::class);

    $this->expectException(UnsupportedUserException::class);

    $this->provider->refreshUser($unsupportedUser);
  }

  /**
   * Method testSupportsClass
   *
   * Tests that supportsClass returns true for SecurityUser.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupportsClass(): void
  {
    $this->assertTrue($this->provider->supportsClass(SecurityUser::class));
  }

  /**
   * Method testSupportsClassReturnsFalseForOtherClasses
   *
   * Tests that supportsClass returns false for other classes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupportsClassReturnsFalseForOtherClasses(): void
  {
    $this->assertFalse($this->provider->supportsClass(UserInterface::class));
  }
  //#endregion
}
