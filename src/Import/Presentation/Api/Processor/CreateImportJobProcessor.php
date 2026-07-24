<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Import\Application\UseCase\Command\CreateImportJob\{CreateImportJobCommand, CreateImportJobResult};
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Service\CsvUploadGuard;
use Import\Presentation\Api\Trait\ImportExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor CreateImportJobProcessor.
 *
 * Handles `POST /imports` (multipart): validates the uploaded CSV
 * (`CsvUploadGuard`), then dispatches `CreateImportJobCommand` on the
 * synchronous command bus — the handler self-enforces the same permission
 * `CreateEquipmentHandler`/`CreateFacilityHandler` require for the job's
 * kind, persists the `pending` job, and enqueues its async processing.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, ImportJobOutput>
 */
final readonly class CreateImportJobProcessor implements ProcessorInterface
{
  use ImportExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CsvUploadGuard $uploadGuard the CSV upload guard
   * @param ImportJobOutputFactory $outputFactory the output factory value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    private CsvUploadGuard $uploadGuard,
    private ImportJobOutputFactory $outputFactory,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImportJobOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $request = $this->requestStack->getCurrentRequest();
    if (!$request instanceof Request) {
      throw new BadRequestHttpException('Request payload is required.');
    }

    $organizationId = $this->resolveOrganizationId($request);
    $uploaded = $this->uploadGuard->fromRequest($request);

    try {
      /** @var CreateImportJobResult $result */
      $result = $this->commandBus->dispatch(new CreateImportJobCommand(
        userId: $user->getId(),
        organizationId: $organizationId,
        kind: $uploaded->kind,
        fileName: $uploaded->fileName,
        contents: $uploaded->contents,
        mimeType: $uploaded->mimeType,
        size: $uploaded->size,
      ));
    } catch (Throwable $exception) {
      throw $this->mapImportException($exception);
    }

    return $this->outputFactory->fromResult($result);
  }

  /**
   * Method resolveOrganizationId.
   *
   * Reads the organization from the `organization` multipart field (an IRI
   * or a raw identifier).
   *
   * @since 1.0.0
   *
   * @param Request $request the current HTTP request
   *
   * @return string the resolved organization identifier
   */
  private function resolveOrganizationId(Request $request): string
  {
    $organization = $request->request->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('Multipart field "organization" is required.');
    }

    return ResourceIriParser::id($organization, 'organizations');
  }
  // #endregion
}
