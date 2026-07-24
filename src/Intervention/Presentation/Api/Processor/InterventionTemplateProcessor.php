<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate\{CreateInterventionTemplateCommand, CreateInterventionTemplateResult};
use Intervention\Application\UseCase\Command\Template\DeleteInterventionTemplate\DeleteInterventionTemplateCommand;
use Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate\{UpdateInterventionTemplateCommand, UpdateInterventionTemplateResult};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionTemplateInput, InterventionTemplateItemInput, UpdateInterventionTemplateInput};
use Intervention\Presentation\Api\Dto\Output\InterventionTemplateOutput;
use Intervention\Presentation\Api\Factory\InterventionTemplateOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{MergePatchFields, ResourceIriParser};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_key_exists;
use function array_map;
use function is_string;

/**
 * Processor InterventionTemplateProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InterventionTemplateOutput|null>
 */
final readonly class InterventionTemplateProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionTemplateProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param InterventionTemplateOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InterventionTemplateOutputFactory $mapper,
    private Security $security,
    private MergePatchFields $mergePatchFields,
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
   * @return ?InterventionTemplateOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionTemplateOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;

    try {
      if ($data instanceof CreateInterventionTemplateInput) {
        /** @var CreateInterventionTemplateResult $result */
        $result = $this->commandBus->dispatch(new CreateInterventionTemplateCommand(
          userId: $user->getId(),
          organizationId: ResourceIriParser::id($data->organization, 'organizations'),
          name: $data->name,
          description: $data->description,
          type: $data->type,
          priority: $data->priority,
          defaultSiteId: null === $data->defaultSite ? null : ResourceIriParser::id($data->defaultSite, 'facilities'),
          defaultResponsibleId: null === $data->defaultResponsible ? null : ResourceIriParser::memberId($data->defaultResponsible),
          duration: $data->duration,
          labelIds: $data->labelIds,
          items: array_map(self::itemPayload(...), $data->items),
        ));

        return $this->mapper->fromView($result->template);
      }

      if ($data instanceof UpdateInterventionTemplateInput) {
        if (null === $id) {
          throw new BadRequestHttpException('The id URI parameter is required.');
        }
        $fields = $this->mergePatchFields->all();
        /** @var UpdateInterventionTemplateResult $result */
        $result = $this->commandBus->dispatch(new UpdateInterventionTemplateCommand(
          userId: $user->getId(),
          templateId: $id,
          name: $data->name,
          description: $data->description,
          type: $data->type,
          priority: $data->priority,
          defaultSiteId: null === $data->defaultSite ? null : ResourceIriParser::id($data->defaultSite, 'facilities'),
          defaultResponsibleId: null === $data->defaultResponsible ? null : ResourceIriParser::memberId($data->defaultResponsible),
          duration: $data->duration,
          labelIds: $data->labelIds,
          items: null === $data->items ? null : array_map(self::itemPayload(...), $data->items),
          hasName: array_key_exists('name', $fields),
          hasDescription: array_key_exists('description', $fields),
          hasType: array_key_exists('type', $fields),
          hasPriority: array_key_exists('priority', $fields),
          hasDefaultSiteId: array_key_exists('defaultSite', $fields),
          hasDefaultResponsibleId: array_key_exists('defaultResponsible', $fields),
          hasDuration: array_key_exists('duration', $fields),
          hasLabelIds: array_key_exists('labelIds', $fields),
          hasItems: array_key_exists('items', $fields),
        ));

        return $this->mapper->fromView($result->template);
      }

      if (null === $id) {
        throw new BadRequestHttpException('The id URI parameter is required.');
      }
      $this->commandBus->dispatch(new DeleteInterventionTemplateCommand($user->getId(), $id));

      return null;
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }
  }

  /**
   * Method itemPayload.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateItemInput $item the item input value
   *
   * @return array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}
   */
  private static function itemPayload(InterventionTemplateItemInput $item): array
  {
    return [
      'action' => $item->action,
      'target' => $item->target,
      'resultResource' => $item->resultResource,
      'required' => $item->required,
      'defaultAssigneeId' => null === $item->defaultAssignee ? null : ResourceIriParser::memberId($item->defaultAssignee),
    ];
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
