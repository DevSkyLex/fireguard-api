<?php

declare(strict_types=1);

namespace Tests\Integration\Auth\Infrastructure\User;

use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Infrastructure\Adapter\User\UserAuthenticationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

    /** @var QueryBusPort $queryBus */
    $queryBus = $container->get(QueryBusPort::class);
    $this->adapter = new UserAuthenticationAdapter(queryBus: $queryBus);
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
