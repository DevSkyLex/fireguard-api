<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate\{
  InstantiateInterventionTemplateCommand,
  InstantiateInterventionTemplateResult
};
use Intervention\Presentation\Api\Dto\Input\InstantiateInterventionTemplateInput;
use Intervention\Presentation\Api\Dto\Output\InstantiateInterventionTemplateOutput;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor InstantiateInterventionTemplateProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<InstantiateInterventionTemplateInput, InstantiateInterventionTemplateOutput>
 */
final readonly class InstantiateInterventionTemplateProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InstantiateInterventionTemplateProcessor class.
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
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return InstantiateInterventionTemplateOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InstantiateInterventionTemplateOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    if (null === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    $input = $data instanceof InstantiateInterventionTemplateInput ? $data : new InstantiateInterventionTemplateInput();

    try {
      /** @var InstantiateInterventionTemplateResult $result */
      $result = $this->commandBus->dispatch(new InstantiateInterventionTemplateCommand(
        userId: $user->getId(),
        templateId: $id,
        name: $input->name,
        siteId: null === $input->site ? null : ResourceIriParser::id($input->site, 'facilities'),
        responsibleId: null === $input->responsible ? null : ResourceIriParser::memberId($input->responsible),
        plannedStartAt: null === $input->plannedStartAt ? null : new DateTimeImmutable($input->plannedStartAt),
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    $output = new InstantiateInterventionTemplateOutput();
    $output->interventionId = $result->interventionId;
    $output->number = $result->number;

    return $output;
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
