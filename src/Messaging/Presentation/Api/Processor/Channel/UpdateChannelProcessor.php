<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Channel\UpdateChannel\{UpdateChannelCommand, UpdateChannelResult};
use Messaging\Presentation\Api\Dto\Input\Channel\UpdateChannelInput;
use Messaging\Presentation\Api\Dto\Output\ChannelOutput;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor UpdateChannelProcessor.
 *
 * Handles `PATCH /api/channels/{id}` (rename and/or archive/unarchive).
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateChannelInput, ChannelOutput>
 */
final readonly class UpdateChannelProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param ChannelOutputFactory $mapper the mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private ChannelOutputFactory $mapper,
    private Security $security,
  ) {
  }

  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ChannelOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChannelOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    if (null === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    if (!$data instanceof UpdateChannelInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    try {
      /** @var UpdateChannelResult $result */
      $result = $this->commandBus->dispatch(new UpdateChannelCommand(
        userId: $user->getId(),
        conversationId: $id,
        name: $data->name,
        isArchived: $data->isArchived,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return $this->mapper->fromView($result->channel);
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
