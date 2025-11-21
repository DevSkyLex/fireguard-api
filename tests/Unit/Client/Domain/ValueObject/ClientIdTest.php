<?php

declare(strict_types=1);

namespace Tests\Client\Domain\ValueObject;

use Client\Domain\ValueObject\ClientId;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ClientIdTest
 * @final
 *
 * Test class for the ClientId value object.
 *
 * @category ValueObject Tests
 * @package Tests\Client\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientIdTest extends TestCase
{
	//#region Constants
	/**
	 * Constant VALID_UUID
	 *
	 * Valid UUID
	 *
	 * @access private
	 *
	 * @var string VALID_UUID
	 */
	private const string VALID_UUID = '123e4567-e89b-12d3-a456-426614174000';

	/**
	 * Constant INVALID_UUID
	 *
	 * Invalid UUID
	 *
	 * @access private
	 *
	 * @var string INVALID_UUID
	 */
	private const string INVALID_UUID = 'invalid-uuid';
	//#endregion

	//#region Methods
	/**
	 * Method testValidClientIdIsAccepted
	 *
	 * Test the constructor with
	 * a valid UUID
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	public function testValidClientIdIsAccepted(): void
	{
		$clientId = new ClientId(value: self::VALID_UUID);

		self::assertSame(
			expected: self::VALID_UUID,
			actual: $clientId->value
		);
	}

	/**
	 * Method testInvalidClientIdThrowsException
	 *
	 * Test the constructor with
	 * an invalid UUID
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	public function testInvalidClientIdThrowsException(): void
	{
		$this->expectException(exception: InvalidValueException::class);

		new ClientId(value: self::INVALID_UUID);
	}

	/**
	 * Method testEqualsReturnsTrueForSameValue
	 *
	 * Test the equals method with
	 * the same value
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	public function testEqualsReturnsTrueForSameValue(): void
	{
		$clientId1 = new ClientId(value: self::VALID_UUID);
		$clientId2 = new ClientId(value: self::VALID_UUID);

		self::assertTrue(condition: $clientId1->equals(other: $clientId2));
	}

	/**
	 * Method testEqualsReturnsFalseForDifferentValue
	 *
	 * Test the equals method with
	 * different values
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	public function testEqualsReturnsFalseForDifferentValue(): void
	{
		$clientId1 = new ClientId(value: self::VALID_UUID);
		$clientId2 = new ClientId(value: '987e6543-e89b-12d3-a456-426614174999');

		self::assertFalse(condition: $clientId1->equals(other: $clientId2));
	}

	/**
	 * Method testToStringReturnsValue
	 *
	 * Test the __toString method
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	public function testToStringReturnsValue(): void
	{
		$clientId = new ClientId(value: self::VALID_UUID);

		self::assertSame(
			expected: self::VALID_UUID,
			actual: (string) $clientId
		);
	}
	//#endregion
}

