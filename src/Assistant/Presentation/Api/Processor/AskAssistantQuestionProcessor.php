<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\{AskAssistantQuestionCommand, AskAssistantQuestionResult};
use Assistant\Presentation\Api\Dto\Input\AskAssistantQuestionInput;
use Assistant\Presentation\Api\Dto\Output\AskAssistantQuestionOutput;
use Assistant\Presentation\Api\Factory\AssistantMessageOutputFactory;
use Assistant\Presentation\Api\Trait\AssistantExceptionMapperTrait;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor AskAssistantQuestionProcessor.
 *
 * Thin: authorization is self-enforced by
 * {@see \Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\AskAssistantQuestionHandler}.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<AskAssistantQuestionInput, AskAssistantQuestionOutput>
 */
final readonly class AskAssistantQuestionProcessor implements ProcessorInterface
{
  use AssistantExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param AssistantMessageOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private AssistantMessageOutputFactory $outputFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AskAssistantQuestionOutput
  {
    /** @var AskAssistantQuestionInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $threadId = $uriVariables['threadId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($threadId) || '' === $threadId) {
      throw new BadRequestHttpException('OrganizationId and threadId URI parameters are required.');
    }

    try {
      /** @var AskAssistantQuestionResult $result */
      $result = $this->commandBus->dispatch(new AskAssistantQuestionCommand(
        organizationId: $organizationId,
        threadId: $threadId,
        actorUserId: $user->getId(),
        body: $data->body,
        temperature: $data->temperature,
      ));
    } catch (Throwable $exception) {
      throw $this->mapAssistantException($exception);
    }

    $output = new AskAssistantQuestionOutput();
    $output->threadId = $result->threadId;
    $output->organizationId = $result->organizationId;
    $output->userMessage = $this->outputFactory->fromView($result->userMessage);
    $output->assistantMessage = $this->outputFactory->fromView($result->assistantMessage);

    return $output;
  }
  // #endregion
}
