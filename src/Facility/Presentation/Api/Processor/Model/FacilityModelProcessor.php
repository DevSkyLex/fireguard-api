<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Model;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Command\Model\ActivateFacilityModel\{ActivateFacilityModelCommand, ActivateFacilityModelResult};
use Facility\Application\UseCase\Command\Model\DeleteFacilityModel\DeleteFacilityModelCommand;
use Facility\Application\UseCase\Command\Model\UpdateFacilityModel\{UpdateFacilityModelCommand, UpdateFacilityModelResult};
use Facility\Application\UseCase\Command\Model\UploadFacilityModel\{UploadFacilityModelCommand, UploadFacilityModelResult};
use Facility\Presentation\Api\Dto\Input\Model\UpdateFacilityModelInput;
use Facility\Presentation\Api\Dto\Output\Model\FacilityModelOutput;
use Facility\Presentation\Api\Operation\FacilityModelOperations;
use Facility\Presentation\Api\Service\FacilityRevisionGuard;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

use function in_array;
use function is_string;

use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;

/**
 * Processor FacilityModelProcessor. HTTP translation only; handlers authorize and validate.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateFacilityModelInput|null, FacilityModelOutput|null>
 */
final readonly class FacilityModelProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commands the commands
   * @param Security $security the security
   * @param RequestStack $requests the requests
   * @param FacilityRevisionGuard $revisions the revisions
   *
   * @return void no return value
   */
  public function __construct(
    private CommandBusPort $commands,
    private Security $security,
    private RequestStack $requests,
    private FacilityRevisionGuard $revisions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Translates HTTP input into typed commands and maps the resulting model metadata.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return ?FacilityModelOutput the operation result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FacilityModelOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    if (FacilityModelOperations::UPLOAD === $operation->getName()) {
      $request = $this->requests->getCurrentRequest();
      $file = $request?->files->get('file');
      if ($file instanceof UploadedFile && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new UnprocessableEntityHttpException('The HTTP upload size limit was exceeded.');
      }
      if (!$file instanceof UploadedFile || !$file->isValid()) {
        throw new BadRequestHttpException('Multipart field "file" must be a valid uploaded GLB.');
      }
      $size = $file->getSize();
      if (false === $size || $size < 20 || $size > 10 * 1024 * 1024) {
        throw new UnprocessableEntityHttpException('A GLB of at most 10 MiB is required.');
      }
      /** @var UploadFacilityModelResult $result */
      $result = $this->commands->dispatch(new UploadFacilityModelCommand(
        $user->getId(),
        $this->identifier($uriVariables, 'organizationId'),
        $this->identifier($uriVariables, 'buildingId'),
        $file->getClientOriginalName(),
        $file->getContent(),
      ));
      $request?->attributes->set('_api_write_item_iri', '/api/facility-models/' . $result->model->id);

      return FacilityModelOutput::fromView($result->model);
    }
    $id = $this->identifier($uriVariables, 'id');
    $revision = $this->revisions->expectedRevision();
    if (FacilityModelOperations::DELETE === $operation->getName()) {
      $this->commands->dispatch(new DeleteFacilityModelCommand($user->getId(), $id, $revision));

      return null;
    }
    if (FacilityModelOperations::ACTIVATE === $operation->getName()) {
      /** @var ActivateFacilityModelResult $result */
      $result = $this->commands->dispatch(new ActivateFacilityModelCommand($user->getId(), $id, $revision));
    } else {
      if (!$data instanceof UpdateFacilityModelInput || null === $data->transform) {
        throw new BadRequestHttpException('A complete transform is required.');
      }
      /** @var UpdateFacilityModelResult $result */
      $result = $this->commands->dispatch(new UpdateFacilityModelCommand($user->getId(), $id, $revision, $data->transform, $data->bindings, $data->removeBindingNodeIndices));
    }

    return FacilityModelOutput::fromView($result->model);
  }

  /**
   * Method identifier.
   *
   * Reads a required transport identifier without performing a business lookup.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $variables
   * @param string $key the key
   *
   * @return string the operation result
   */
  private function identifier(array $variables, string $key): string
  {
    $id = $variables[$key] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new BadRequestHttpException('Missing model URI identifier.');
    }

    return $id;
  }
  // #endregion
}
