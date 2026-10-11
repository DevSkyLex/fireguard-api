<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\{AddInspectionAttachmentCommand, AddInspectionAttachmentResult};
use Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment\DeleteInspectionAttachmentCommand;
use Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\{ResolveInspectionMediaQuery, ResolveInspectionMediaResult};
use Inspection\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput;
use Inspection\Presentation\Api\Http\RevisionGuard;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/** InspectionMediaProcessor translates HTTP media requests through use cases.
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InspectionAttachmentOutput|null>
 */
final readonly class InspectionMediaProcessor implements ProcessorInterface
{
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
    private MultipartAttachmentGuard $attachmentGuard,
    private RevisionGuard $revisionGuard,
  ) {
  }

  /** @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InspectionAttachmentOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $this->delete($user, $uriVariables);

      return null;
    }

    return $this->upload($user, $uriVariables);
  }

  /**
   * Method delete
   *
   * Reads the authorized attachment before translating its revision precondition.
   *
   * @access private
   *
   * @param AuthenticatedUser $user the authenticated actor
   * @param array<string, mixed> $uriVariables the attachment URI values
   *
   * @return void
   */
  private function delete(AuthenticatedUser $user, array $uriVariables): void
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    /** @var ResolveInspectionMediaResult $scope */
    $scope = $this->queryBus->ask(new ResolveInspectionMediaQuery(userId: $user->getId(), attachmentId: $id, write: true, deletion: true));
    if (null === $scope->attachment) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    $this->revisionGuard->assertMatches($scope->attachment->revision);
    $this->commandBus->dispatch(new DeleteInspectionAttachmentCommand(organizationId: $scope->organizationId, inspectionId: $scope->inspectionId, attachmentId: $scope->attachment->id, expectedRevision: $scope->attachment->revision, userId: $user->getId()));

  }

  /**
   * Method upload
   *
   * Resolves the authorized parent before reading the multipart payload.
   *
   * @access private
   *
   * @param AuthenticatedUser $user the authenticated actor
   * @param array<string, mixed> $uriVariables the parent URI values
   *
   * @return InspectionAttachmentOutput the uploaded attachment projection
   */
  private function upload(AuthenticatedUser $user, array $uriVariables): InspectionAttachmentOutput
  {
    $nonConformityId = $this->parentId($uriVariables, 'nonConformityId');
    $inspectionId = $this->parentId($uriVariables, 'inspectionId');
    if (null === $inspectionId && null === $nonConformityId) {
      throw new BadRequestHttpException('The inspectionId URI parameter is required.');
    }
    /** @var ResolveInspectionMediaResult $scope */
    $scope = $this->queryBus->ask(new ResolveInspectionMediaQuery(userId: $user->getId(), inspectionId: $inspectionId, nonConformityId: $nonConformityId, write: true));
    $uploaded = $this->attachmentGuard->fromRequest($this->currentRequest());
    /** @var AddInspectionAttachmentResult $result */
    $result = $this->commandBus->dispatch(new AddInspectionAttachmentCommand(
      organizationId: $scope->organizationId,
      inspectionId: $scope->inspectionId,
      fileName: $uploaded->fileName,
      contents: $uploaded->contents,
      mimeType: $uploaded->mimeType,
      size: $uploaded->size,
      nonConformityId: $nonConformityId,
      label: $uploaded->label,
      userId: $user->getId(),
    ));
    $output = new InspectionAttachmentOutput();
    $output->id = $result->attachmentId;
    $output->inspectionId = $result->inspectionId;
    $output->fileName = $result->fileName;
    $output->mimeType = $result->mimeType;
    $output->size = $result->size;
    $output->label = $result->label;
    $output->revision = $result->revision;
    $output->uploadedAt = $result->uploadedAt->format('c');
    $output->nonConformityId = $result->nonConformityId;

    return $output;
  }

  /**
   * Method parentId
   *
   * Distinguishes an absent parent from a malformed URI value.
   *
   * @access private
   *
   * @param array<string, mixed> $uriVariables the route values
   * @param string $name the requested parent parameter
   *
   * @return ?string the optional validated parent identifier
   */
  private function parentId(array $uriVariables, string $name): ?string
  {
    $value = $uriVariables[$name] ?? null;
    if (isset($uriVariables[$name]) && (!is_string($value) || '' === $value)) {
      throw new BadRequestHttpException('The ' . $name . ' URI parameter is required.');
    }

    return is_string($value) ? $value : null;
  }

  private function currentRequest(): Request
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request instanceof Request) {
      throw new BadRequestHttpException('Request payload is required.');
    }

    return $request;
  }
}
