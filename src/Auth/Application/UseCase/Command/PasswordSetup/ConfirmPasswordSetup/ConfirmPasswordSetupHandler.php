<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordSetup\ConfirmPasswordSetup;

use Auth\Application\Port\Inbound\Federation\PasswordSetupPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Shared\Application\Message\CommandHandler;

/**
 * ConfirmPasswordSetupHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordSetupHandler implements CommandHandler
{
  public function __construct(private PasswordSetupPort $passwords)
  {
  }

  public function __invoke(ConfirmPasswordSetupCommand $command): ConfirmPasswordSetupResult
  {
    try {
      $confirmation = $this->passwords->confirm($command->userId, $command->token, $command->code, $command->password);

      return new ConfirmPasswordSetupResult(true, $confirmation['attemptsRemaining']);
    } catch (FederatedAuthException $exception) {
      return new ConfirmPasswordSetupResult(false, 'invalid_code' === $exception->errorCode ? (int) $exception->getMessage() : 0, $exception->errorCode);
    }
  }
}
