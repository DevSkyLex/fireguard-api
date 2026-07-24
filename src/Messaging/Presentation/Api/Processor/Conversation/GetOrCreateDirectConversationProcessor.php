<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation\{GetOrCreateDirectConversationCommand, GetOrCreateDirectConversationResult};
use Messaging\Presentation\Api\Dto\Input\GetOrCreateDirectConversationInput;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

/**
 * Processor GetOrCreateDirectConversationProcessor.
 *
 * Handles `POST /api/direct-conversations` — idempotent get-or-create of a
 * 1-to-1 direct conversation with another organization member (L2.4).
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<GetOrCreateDirectConversationInput, ConversationOutput>
 */
final readonly class GetOrCreateDirectConversationProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param ConversationOutputFactory $mapper the mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private ConversationOutputFactory $mapper,
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
   * @return ConversationOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConversationOutput
  {
    $user = $this->user();

    if (!$data instanceof GetOrCreateDirectConversationInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    $organizationId = ResourceIriParser::id($data->organization, 'organizations');

    try {
      /** @var GetOrCreateDirectConversationResult $result */
      $result = $this->commandBus->dispatch(new GetOrCreateDirectConversationCommand(
        userId: $user->getId(),
        organizationId: $organizationId,
        otherMemberId: $data->memberId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return $this->mapper->fromView($result->conversation);
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
