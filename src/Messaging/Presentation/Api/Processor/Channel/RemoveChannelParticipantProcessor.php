<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant\RemoveChannelParticipantCommand;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor RemoveChannelParticipantProcessor.
 *
 * Handles `DELETE /api/channels/{id}/participants/{memberId}`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, null>
 */
final readonly class RemoveChannelParticipantProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
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
   * @return null the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    $memberId = is_string($uriVariables['memberId'] ?? null) ? $uriVariables['memberId'] : null;
    if (null === $id || null === $memberId) {
      throw new BadRequestHttpException('The id and memberId URI parameters are required.');
    }

    try {
      $this->commandBus->dispatch(new RemoveChannelParticipantCommand(
        userId: $user->getId(),
        conversationId: $id,
        memberId: $memberId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return null;
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
