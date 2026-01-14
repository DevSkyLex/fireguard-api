<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpResult};
use Otp\Presentation\Api\Dto\Output\Totp\SetupTotpOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function is_string;
use function method_exists;

/**
 * Processor SetupTotpProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, SetupTotpOutput>
 */
final readonly class SetupTotpProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SetupTotpOutput
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $userId = $user->getUserIdentifier();
    $accountNameRaw = method_exists($user, 'getEmail') ? $user->getEmail() : null;
    $accountName = is_string($accountNameRaw) ? $accountNameRaw : $userId;

    $command = new SetupTotpCommand(
      userId: $userId,
      accountName: $accountName,
    );

    /** @var SetupTotpResult $result */
    $result = $this->commandBus->dispatch($command);

    $output = new SetupTotpOutput();
    $output->secret = $result->secret;
    $output->qrCodeUri = $result->qrCodeUri;

    return $output;
  }
  // #endregion
}
