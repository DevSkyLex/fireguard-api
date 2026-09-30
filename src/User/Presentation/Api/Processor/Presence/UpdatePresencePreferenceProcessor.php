<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\Presence;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use User\Application\UseCase\Command\Presence\UpdatePresencePreference\{UpdatePresencePreferenceCommand, UpdatePresencePreferenceResult};
use User\Presentation\Api\Dto\Input\Presence\UpdatePresencePreferenceInput;
use User\Presentation\Api\Dto\Output\Presence\PresencePreferenceOutput;

use function is_bool;

/**
 * @implements ProcessorInterface<mixed, PresencePreferenceOutput>
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePresencePreferenceProcessor implements ProcessorInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private CommandBusPort $commandBus, private CurrentActorPort $actor)
  {
  }

  /**
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PresencePreferenceOutput
  {
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    if (!$data instanceof UpdatePresencePreferenceInput
      || (null !== $data->doNotDisturb && !is_bool($data->doNotDisturb))
      || (null !== $data->invisible && !is_bool($data->invisible))
      || (null === $data->doNotDisturb && null === $data->invisible)) {
      throw new BadRequestHttpException('At least one presence preference boolean is required.');
    }
    /**
     * @var UpdatePresencePreferenceResult $result
     */
    $result = $this->commandBus->dispatch(new UpdatePresencePreferenceCommand($userId, $data->doNotDisturb, $data->invisible));
    $output = new PresencePreferenceOutput();
    $output->doNotDisturb = $result->doNotDisturb;
    $output->revision = $result->revision;
    $output->invisible = $result->invisible;

    return $output;
  }
}
