<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Activity\AddInterventionComment\{AddInterventionCommentCommand, AddInterventionCommentResult};
use Intervention\Presentation\Api\Dto\Input\CreateInterventionCommentInput;
use Intervention\Presentation\Api\Dto\Output\InterventionActivityOutput;
use Intervention\Presentation\Api\Factory\InterventionActivityOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};
use Throwable;

use function is_string;
use function strip_tags;
use function trim;

/**
 * Processor InterventionActivityProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateInterventionCommentInput, InterventionActivityOutput>
 */
final readonly class InterventionActivityProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionActivityProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param InterventionActivityOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param HtmlSanitizerInterface $commentSanitizer the rich-text comment sanitizer
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InterventionActivityOutputFactory $mapper,
    private Security $security,
    private HtmlSanitizerInterface $commentSanitizer,
  ) {
  }

  /**
   * Method process.
   *
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return InterventionActivityOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionActivityOutput
  {
    if (!$data instanceof CreateInterventionCommentInput) {
      throw new BadRequestHttpException('Invalid comment payload.');
    }
    $user = $this->user();
    $interventionId = $uriVariables['interventionId'] ?? null;
    if (!is_string($interventionId) || '' === $interventionId) {
      throw new BadRequestHttpException('The interventionId URI parameter is required.');
    }
    $body = $this->commentSanitizer->sanitize($data->body);
    if ('' === trim(strip_tags($body))) {
      throw new UnprocessableEntityHttpException('The comment body cannot be empty.');
    }

    try {
      /** @var AddInterventionCommentResult $result */
      $result = $this->commandBus->dispatch(new AddInterventionCommentCommand(
        userId: $user->getId(),
        interventionId: $interventionId,
        body: $body,
        clientId: $data->clientId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return $this->mapper->fromView($result->view);
  }

  /**
   * Method user.
   *
   * Executes the user operation.
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
