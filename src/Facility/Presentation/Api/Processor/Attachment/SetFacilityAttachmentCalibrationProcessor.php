<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration\{SetFacilityAttachmentCalibrationCommand, SetFacilityAttachmentCalibrationResult};
use Facility\Presentation\Api\Dto\Input\Attachment\SetFacilityAttachmentCalibrationInput;
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Service\FacilityRevisionGuard;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function array_key_exists;
use function get_object_vars;
use function is_string;

/**
 * Processor SetFacilityAttachmentCalibrationProcessor.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<SetFacilityAttachmentCalibrationInput, FacilityAttachmentOutput>
 */
final readonly class SetFacilityAttachmentCalibrationProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(private CommandBusPort $commandBus, private Security $security, private FacilityRevisionGuard $revisionGuard)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityAttachmentOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || !array_key_exists('calibration', get_object_vars($data))) {
      throw new BadRequestHttpException('The attachment id and calibration field are required.');
    }

    /** @var SetFacilityAttachmentCalibrationResult $result */
    $result = $this->commandBus->dispatch(new SetFacilityAttachmentCalibrationCommand($user->getId(), $id, $this->revisionGuard->expectedRevision(), $data->calibration));
    $output = new FacilityAttachmentOutput();
    $output->id = $result->id;
    $output->facilityId = $result->facilityId;
    $output->fileName = $result->fileName;
    $output->mimeType = $result->mimeType;
    $output->size = $result->size;
    $output->label = $result->label;
    $output->revision = $result->revision;
    $output->kind = $result->kind;
    $output->isPrimaryPlan = $result->isPrimaryPlan;
    $output->imageWidth = $result->imageWidth;
    $output->imageHeight = $result->imageHeight;
    $output->uploadedAt = $result->uploadedAt;
    $output->calibration = $result->calibration;
    $output->calibrationBuildingId = $result->calibrationBuildingId;
    $output->calibrationIssue = $result->calibrationIssue;

    return $output;
  }
  // #endregion
}
