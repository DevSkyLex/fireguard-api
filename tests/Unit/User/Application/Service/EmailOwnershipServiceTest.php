<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Helper\TestEventIdProvider;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\{EmailOwnershipRepositoryPort, UserRepositoryPort};
use User\Application\Service\EmailOwnershipService;
use User\Domain\Exception\EmailOwnershipUnavailableException;

/**
 * Test EmailOwnershipServiceTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipServiceTest extends TestCase
{
  // #region Tests
  /**
   * @since 1.0.0
   */
  #[Test]
  public function legacyVerifiedAccountStillNeedsProof(): void
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $user = UserTestFactory::createActive();
    $users->method('findById')->willReturn($user);
    $service = new EmailOwnershipService($users, $this->createStub(EmailOwnershipRepositoryPort::class));
    $result = $service->get($user->id()->value);
    self::assertSame($user->email()->value, $result->email);
    self::assertFalse($result->verified);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function missingAccountCannotBeDiscovered(): void
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn(null);
    $service = new EmailOwnershipService($users, $this->createStub(EmailOwnershipRepositoryPort::class));
    $this->expectException(EmailOwnershipUnavailableException::class);
    $service->get('11111111-1111-4111-8111-111111111111');
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function inactiveAccountCannotUseExistingProof(): void
  {
    $user = UserTestFactory::createActive();
    $user->confirmEmailOwnership($user->email());
    $user->deactivate(new TestEventIdProvider());
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn($user);
    $service = new EmailOwnershipService($users, $this->createStub(EmailOwnershipRepositoryPort::class));
    $this->expectException(EmailOwnershipUnavailableException::class);
    $service->get($user->id()->value);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function compareAndSetRefusalDoesNotPretendSuccess(): void
  {
    $proofs = $this->createMock(EmailOwnershipRepositoryPort::class);
    $proofs->expects(self::once())->method('confirm')->with('user', 'old@business.example')->willReturn(false);
    $service = new EmailOwnershipService($this->createStub(UserRepositoryPort::class), $proofs);
    $this->expectException(EmailOwnershipUnavailableException::class);
    $service->confirm('user', 'old@business.example');
  }
  // #endregion
}
