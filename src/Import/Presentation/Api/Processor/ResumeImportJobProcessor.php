<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Import\Application\UseCase\Command\ResumeImportJob\ResumeImportJobCommand;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Trait\ImportExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/** @implements ProcessorInterface<mixed, ImportJobOutput> */
final readonly class ResumeImportJobProcessor implements ProcessorInterface
{
  use ImportExceptionMapperTrait;

  public function __construct(private CommandBusPort $commands, private Security $security, private ImportJobOutputFactory $output)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImportJobOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new BadRequestHttpException('An import identifier is required.');
    }

    try {
      /** @var GetImportJobResult $result */
      $result = $this->commands->dispatch(new ResumeImportJobCommand($user->getId(), $id));

      return $this->output->fromView($result);
    } catch (Throwable $exception) {
      throw $this->mapImportException($exception);
    }
  }
}
