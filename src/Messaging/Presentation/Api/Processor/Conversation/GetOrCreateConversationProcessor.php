<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation\{GetOrCreateConversationCommand, GetOrCreateConversationResult};
use Messaging\Presentation\Api\Dto\Input\GetOrCreateConversationInput;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

/**
 * Processor GetOrCreateConversationProcessor.
 *
 * Handles `POST /api/conversations` — idempotent get-or-create by subject.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<GetOrCreateConversationInput, ConversationOutput>
 */
final readonly class GetOrCreateConversationProcessor implements ProcessorInterface
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

    if (!$data instanceof GetOrCreateConversationInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    $organizationId = ResourceIriParser::id($data->organization, 'organizations');
    $subjectSegment = ConversationOutputFactory::subjectResourceSegment($data->subjectType);
    $subjectId = ResourceIriParser::id($data->subject, $subjectSegment);

    try {
      /** @var GetOrCreateConversationResult $result */
      $result = $this->commandBus->dispatch(new GetOrCreateConversationCommand(
        userId: $user->getId(),
        organizationId: $organizationId,
        subjectType: $data->subjectType,
        subjectId: $subjectId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return $this->mapper->fromView($result->conversation, $result->subjectLabel);
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
