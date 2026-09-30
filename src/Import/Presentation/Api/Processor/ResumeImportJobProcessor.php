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

  // #region Constructor
  /**
   * Method __construct
   *
   * Connects authenticated import-resume requests to the command bus and output mapper.
   *
   * @access public
   *
   * @param CommandBusPort $commands dispatches the resume command
   * @param Security $security resolves the authenticated user
   * @param ImportJobOutputFactory $output maps import job results
   *
   * @return void
   */
  public function __construct(private CommandBusPort $commands, private Security $security, private ImportJobOutputFactory $output)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method process.
   *
   * Resumes the requested import job for the authenticated user and maps its current state.
   *
   * @access public
   *
   * @param mixed $data the processor input
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables the route variables containing the job identifier
   * @param array<string, mixed> $context the processor context
   *
   * @return ImportJobOutput the resumed import job state
   *
   * @throws AccessDeniedHttpException when the current user is not authenticated
   * @throws BadRequestHttpException when the import job identifier is missing
   */
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
  // #endregion
}
