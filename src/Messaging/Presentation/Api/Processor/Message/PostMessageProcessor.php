<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageResult};
use Messaging\Presentation\Api\Dto\Input\PostMessageInput;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\MessageOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};
use Throwable;

use function is_string;
use function strip_tags;
use function trim;

/**
 * Processor PostMessageProcessor.
 *
 * Handles `POST /api/conversations/{conversationId}/messages`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<PostMessageInput, MessageOutput>
 */
final readonly class PostMessageProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param MessageOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param HtmlSanitizerInterface $messageSanitizer the rich-text message sanitizer
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private MessageOutputFactory $mapper,
    private Security $security,
    private HtmlSanitizerInterface $messageSanitizer,
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
   * @return MessageOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageOutput
  {
    $user = $this->user();
    $conversationId = $uriVariables['conversationId'] ?? null;
    if (!is_string($conversationId) || '' === $conversationId) {
      throw new BadRequestHttpException('The conversationId URI parameter is required.');
    }

    if (!$data instanceof PostMessageInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

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
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return $this->mapper->fromView($result->message, $result->currentMemberId);
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
