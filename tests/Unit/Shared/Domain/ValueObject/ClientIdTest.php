<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\ClientId;

/**
 * Test ClientIdTest
 * @final
 *
 * Test class for ClientId.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientIdTest extends TestCase
{
  //#region Constants
  /**
   * Constant VALID_CLIENT_ID
   *
   * Valid client ID
   *
   * @access private
   *
   * @var string VALID_CLIENT_ID
   */
  private const string VALID_CLIENT_ID = 'client_123';

  /**
   * Constant INVALID_CLIENT_ID
   *
   * Invalid client ID
   *
   * @access private
   *
   * @var string INVALID_CLIENT_ID
   */
  private const string INVALID_CLIENT_ID = '';
  //#endregion

  //#region Methods
  /**
   * Method testValidClientIdIsAccepted
   *
   * Test the constructor with a
   * valid client ID
   *
   * @access public
   *
   * @return void No return value
   */
  public function testValidClientIdIsAccepted(): void
  {
    $clientId = new ClientId(value: self::VALID_CLIENT_ID);

    self::assertSame(
      expected: self::VALID_CLIENT_ID,
      actual: (string) $clientId
    );
  }

  /**
   * Method testInvalidClientIdThrowsException
   *
   * Test the constructor with an
   * invalid client ID
   *
   * @access public
   *
   * @return void No return value
   */
  public function testInvalidClientIdThrowsException(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new ClientId(value: self::INVALID_CLIENT_ID);
  }
  //#endregion
}
