<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Checklist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Checklist\CreateChecklist\{CreateChecklistCommand, CreateChecklistResult};
use Inspection\Presentation\Api\Dto\Input\Checklist\CreateChecklistInput;
use Inspection\Presentation\Api\Dto\Output\Checklist\{ChecklistItemOutput, ChecklistOutput};
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProcessorInterface<CreateChecklistInput, ChecklistOutput> */
final readonly class CreateChecklistProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChecklistOutput
  {
    /** @var CreateChecklistInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    $items = [];
    foreach ($data->items as $item) {
      $items[] = [
        'label' => $item->label,
        'description' => $item->description,
        'required' => $item->required,
        'position' => $item->position,
      ];
    }

    try {
      /** @var CreateChecklistResult $result */
      $result = $this->commandBus->dispatch(new CreateChecklistCommand(
        organizationId: $organizationId,
        name: $data->name,
        version: $data->version,
        items: $items,
      ));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new ChecklistOutput();
    $output->id = $result->checklistId;
    $output->organizationId = $result->organizationId;
    $output->name = $result->name;
    $output->version = $result->version;
    $output->status = $result->status;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    $itemOutputs = [];
    foreach ($result->items as $item) {
      $itemOutput = new ChecklistItemOutput();
      $itemOutput->id = $item['id'];
      $itemOutput->label = $item['label'];
      $itemOutput->position = $item['position'];
      $itemOutput->required = $item['required'];
      $itemOutput->description = $item['description'];
      $itemOutputs[] = $itemOutput;
    }
    $output->items = $itemOutputs;

    return $output;
  }
}
