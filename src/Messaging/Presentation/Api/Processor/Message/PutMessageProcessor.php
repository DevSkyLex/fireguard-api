<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageResult};
use Messaging\Domain\Exception\MessagingClientMessageAlreadyExistsException;
use Messaging\Presentation\Api\Dto\Input\{MessageReferenceInput, PostMessageInput};
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\MessageOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};
use Throwable;

use function array_map;
use function is_string;
use function strip_tags;
use function trim;

/**
 * Processor PutMessageProcessor.
 *
 * Handles `PUT /api/conversations/{conversationId}/messages/{clientId}` — the
 * idempotent, create-only twin of `PostMessageProcessor`.
 *
 * It exists so an offline outbox can be replayed safely. `POST` mints the id
 * server-side, so a queued send whose response was lost would create a second
 * message on retry, with nothing on either side able to detect it. Here the
 * client owns the id: the retry conflicts, the caller treats the conflict as
 * success, and the member's message appears exactly once.
 *
 * `If-None-Match: *` is mandatory, as it is on every other client-uuid create
 * in this codebase: it states the caller's intent to create and never update,
 * so this route can never be mistaken for an edit.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<PostMessageInput, MessageOutput>
 */
final readonly class PutMessageProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param MessageOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param HtmlSanitizerInterface $messageSanitizer the rich-text message sanitizer
   * @param CreationPreconditionGuard $creationPreconditionGuard the create-only precondition guard
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private MessageOutputFactory $mapper,
    private Security $security,
    private HtmlSanitizerInterface $messageSanitizer,
    private CreationPreconditionGuard $creationPreconditionGuard,
  ) {
  }
  // #endregion

  // #region Methods
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
   * @return MessageOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageOutput
  {
    $user = $this->user();

    $conversationId = $uriVariables['conversationId'] ?? null;
    if (!is_string($conversationId) || '' === $conversationId) {
      throw new BadRequestHttpException('The conversationId URI parameter is required.');
    }

    $clientId = $uriVariables['clientId'] ?? null;
    if (!is_string($clientId) || '' === $clientId) {
      throw new BadRequestHttpException('The clientId URI parameter is required.');
    }

    if (!$data instanceof PostMessageInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    $this->creationPreconditionGuard->assertCreateOnly();

    $body = $this->messageSanitizer->sanitize($data->body);
    if ('' === trim(strip_tags($body))) {
      throw new UnprocessableEntityHttpException('The message body cannot be empty.');
    }

    try {
      /** @var PostMessageResult $result */
      $result = $this->commandBus->dispatch(new PostMessageCommand(
        userId: $user->getId(),
        conversationId: $conversationId,
        body: $body,
        references: array_map(self::referenceToArray(...), $data->references),
        clientId: $clientId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapReplayException($exception);
    }

    return $this->mapper->fromView($result->message, $result->currentMemberId);
  }

  /**
   * Method mapReplayException.
   *
   * Maps a replayed client id to the codebase's stable
   * `/problems/client-resource-already-exists` type, and everything else
   * through the module's usual mapping.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the thrown exception value
   *
   * @return Throwable the exception to surface
   */
  private function mapReplayException(Throwable $exception): Throwable
  {
    $current = $exception;
    while (null !== $current) {
      if ($current instanceof MessagingClientMessageAlreadyExistsException) {
        return new ClientResourceAlreadyExistsHttpException(Response::HTTP_CONFLICT, $current);
      }

      $current = $current->getPrevious();
    }

    return $this->mapMessagingException($exception);
  }

  /**
   * Method referenceToArray.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param MessageReferenceInput $reference the reference input value
   *
   * @return array{type: string, id: string, label: ?string, code: ?string} the raw reference shape
   */
  private static function referenceToArray(MessageReferenceInput $reference): array
  {
    return ['type' => $reference->type, 'id' => $reference->id, 'label' => $reference->label, 'code' => $reference->code];
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
  // #endregion
}
