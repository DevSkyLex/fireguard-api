<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Media;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\Contract\Media\EquipmentMediaView;
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\AddAttachmentResult;
use Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\{GetEquipmentMediaQuery, GetEquipmentMediaResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function is_string;

/**
 * Translates authorized attachment projections to HTTP output.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AttachmentOutput>
 */
final readonly class MediaProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @param array<string, mixed> $uriVariables @param array<string, mixed> $context @since 1.0.0
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): AttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Media not found.');
    }
    $user = $this->security->getUser();
    /** @var GetEquipmentMediaResult $result */
    $result = $this->queries->ask(new GetEquipmentMediaQuery($id, $user instanceof AuthenticatedUser ? $user->getId() : null));

    return self::output($result->view);
  }

  /**
   * @since 1.0.0
   */
  public static function output(EquipmentMediaView|AddAttachmentResult $view): AttachmentOutput
  {
    $output = new AttachmentOutput();
    $output->id = $view instanceof EquipmentMediaView ? $view->id : $view->attachmentId;
    $output->equipmentId = $view->equipmentId;
    $output->fileName = $view->fileName;
    $output->mimeType = $view->mimeType;
    $output->size = $view->size;
    $output->label = $view->label;
    $output->revision = $view->revision;
    $output->uploadedAt = $view->uploadedAt->format('c');

    return $output;
  }
  // #endregion
}
