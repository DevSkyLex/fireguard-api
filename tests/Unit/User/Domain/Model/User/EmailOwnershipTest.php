<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Model\User;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use Tests\Support\Factory\UserTestFactory;
use User\Domain\Exception\EmailOwnershipUnavailableException;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{UserId, UserProfile, Username};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;

/**
 * Test EmailOwnershipTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipTest extends TestCase
{
  // #region Tests
  /**
   * @since 1.0.0
   */
  #[Test]
  public function administrativeVerificationDoesNotProvePossession(): void
  {
    $user = UserTestFactory::createActive();
    self::assertTrue($user->isEmailVerified());
    self::assertNull($user->emailOwnershipVerifiedAt());
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function externalIdentityDoesNotProvePossession(): void
  {
    $user = User::registerFederated(new UserId('11111111-1111-4111-8111-111111111111'), new Username('federated'), new Email('member@business.example'), new UserProfile('Test', 'User'), new TestEventIdProvider());
    self::assertTrue($user->isEmailVerified());
    self::assertNull($user->emailOwnershipVerifiedAt());
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function proofRoundTripsThroughPersistenceMapper(): void
  {
    $user = UserTestFactory::createActive();
    $user->confirmEmailOwnership($user->email());
    $mapper = new UserMapper();
    $copy = $mapper->toDomain($mapper->toRecord($user));
    self::assertEquals($user->emailOwnershipVerifiedAt(), $copy->emailOwnershipVerifiedAt());
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function rejectsOldMailbox(): void
  {
    $user = UserTestFactory::createActive();
    $user->changeEmail(new Email('new@business.example'), new TestEventIdProvider());
    $this->expectException(EmailOwnershipUnavailableException::class);
    $user->confirmEmailOwnership(new Email('test@example.com'));
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function confirmedEmailChangeProvesNewAddress(): void
  {
    $user = UserTestFactory::createActive();
    $user->changeEmail(new Email('new@business.example'), new TestEventIdProvider());
    self::assertNotNull($user->emailOwnershipVerifiedAt());
    self::assertSame('new@business.example', $user->email()->value);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function inactiveAccountCannotAcquireProof(): void
  {
    $user = UserTestFactory::createActive();
    $user->deactivate(new TestEventIdProvider());
    $this->expectException(EmailOwnershipUnavailableException::class);
    $user->confirmEmailOwnership($user->email());
  }
  // #endregion
}
