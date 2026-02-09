<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\User;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Application\UseCase\Command\User\ActivateUser\ActivateUserCommand;
use User\Application\UseCase\Command\User\DeactivateUser\DeactivateUserCommand;
use User\Application\UseCase\Command\User\VerifyUserEmail\VerifyUserEmailCommand;

/**
 * Test UserStatusCommandTest.
 *
 * @category Command Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ActivateUserCommand::class)]
#[CoversClass(DeactivateUserCommand::class)]
#[CoversClass(VerifyUserEmailCommand::class)]
final class UserStatusCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCommandsExposeIds(): void
  {
    $activate = new ActivateUserCommand(id: 'user-1');
    $deactivate = new DeactivateUserCommand(id: 'user-2');
    $verify = new VerifyUserEmailCommand(id: 'user-3');

    self::assertSame('user-1', $activate->id);
    self::assertSame('user-2', $deactivate->id);
    self::assertSame('user-3', $verify->id);
  }
  // #endregion
}
