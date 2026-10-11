<?php

declare(strict_types=1);

namespace Tests\Integration\Auth\Infrastructure\User;

use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Infrastructure\Adapter\User\UserAuthenticationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Application\Port\Inbound\UserAuthenticationPort;

use function bin2hex;
use function random_bytes;

/**
 * Test UserAuthenticationAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserAuthenticationAdapter::class)]
final class UserAuthenticationAdapterTest extends KernelTestCase
{
  // #region Properties
  private UserAuthenticationAdapter $adapter;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var UserAuthenticationPort $queryBus */
    $queryBus = $container->get(UserAuthenticationPort::class);
    $this->adapter = new UserAuthenticationAdapter(queryBus: $queryBus, logger: new NullLogger());
  }

  protected function tearDown(): void
  {
    self::ensureKernelShutdown();
    parent::tearDown();
  }
  // #endregion

  // #region Authenticate Tests
  /**
   * Method testAuthenticateReturnsFailedForUnknownUser.
   */
  #[Test]
  public function testAuthenticateReturnsFailedForUnknownUser(): void
  {
    $email = 'nobody-' . bin2hex(random_bytes(8)) . '@example.test';

    $result = $this->adapter->authenticate(email: $email, password: 'wrong-password');

    $this->assertInstanceOf(UserAuthenticationResult::class, $result);
    $this->assertFalse($result->authenticated);
    $this->assertNull($result->userId);
  }

  /**
   * Method testAuthenticateReturnsFailedForEmptyCredentials.
   */
  #[Test]
  public function testAuthenticateReturnsFailedForEmptyCredentials(): void
  {
    $result = $this->adapter->authenticate(email: '', password: '');

    $this->assertInstanceOf(UserAuthenticationResult::class, $result);
    $this->assertFalse($result->authenticated);
  }
  // #endregion
}
