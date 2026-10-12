<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Adapter\User;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use User\Infrastructure\Adapter\User\SignInEligibilityAdapter;

/**
 * Class SignInEligibilityAdapterTest
 *
 * Pins fresh-status denial and the explicit verification waiver without ORM or cached account projections.
 *
 * @category Adapter Tests
 */
#[CoversClass(SignInEligibilityAdapter::class)]
final class SignInEligibilityAdapterTest extends TestCase
{
  // #region Constants
  /**
   * Constant USER_ID
   *
   * @var string
   */
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';
  // #endregion

  // #region Methods
  /**
   * Method states
   *
   * @access public
   *
   * @return iterable<string, array{string|false, bool, bool}> stored status, verification choice and outcome
   */
  public static function states(): iterable
  {
    yield 'active default' => ['active', true, true];
    yield 'active waiver' => ['active', false, true];
    yield 'pending default' => ['pending_verification', true, false];
    yield 'pending waiver' => ['pending_verification', false, true];
    yield 'inactive default' => ['inactive', true, false];
    yield 'inactive waiver' => ['inactive', false, false];
    yield 'locked default' => ['locked', true, false];
    yield 'locked waiver' => ['locked', false, false];
    yield 'missing default' => [false, true, false];
    yield 'missing waiver' => [false, false, false];
    yield 'unknown default' => ['future_status', true, false];
    yield 'unknown waiver' => ['future_status', false, false];
  }

  #[Test]
  #[DataProvider('states')]
  public function testStoredStateAndPolicyDecideEligibility(string|false $status, bool $requireVerification, bool $eligible): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchOne')->with('SELECT status FROM users WHERE id = :id', ['id' => self::USER_ID])->willReturn($status);
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('getConnection')->willReturn($connection);
    $manager->expects(self::never())->method('find');
    self::assertSame($eligible, new SignInEligibilityAdapter($manager)->isEligible(self::USER_ID, $requireVerification));
  }

  #[Test]
  public function testMalformedIdentifierDeniesWithoutStorageLookup(): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::never())->method('getConnection');
    self::assertFalse(new SignInEligibilityAdapter($manager)->isEligible('invalid', false));
  }

  #[Test]
  public function testSubsequentCheckReadsChangedAccountStatus(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls('active', 'inactive');
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::exactly(2))->method('getConnection')->willReturn($connection);
    $manager->expects(self::never())->method('find');
    $adapter = new SignInEligibilityAdapter($manager);
    self::assertTrue($adapter->isEligible(self::USER_ID, false));
    self::assertFalse($adapter->isEligible(self::USER_ID, false));
  }

  #[Test]
  public function testStorageFailureCannotBecomeAnAllowedResult(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchOne')->willThrowException(new RuntimeException('storage unavailable'));
    $manager = $this->createStub(EntityManagerInterface::class);
    $manager->method('getConnection')->willReturn($connection);
    $this->expectException(RuntimeException::class);
    new SignInEligibilityAdapter($manager)->isEligible(self::USER_ID, false);
  }
  // #endregion
}
