<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\{AddInterventionAttachmentCommand, AddInterventionAttachmentResult};
use Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment\DeleteInterventionAttachmentCommand;
use Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia\{ResolveInterventionMediaQuery, ResolveInterventionMediaResult};
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Intervention\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Intervention\Presentation\Api\Http\RevisionGuard;
use Intervention\Presentation\Api\Provider\Attachment\InterventionMediaProvider;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/** InterventionMediaProcessor translates HTTP media requests through use cases.
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InterventionAttachmentOutput|null>
 */
final readonly class InterventionMediaProcessor implements ProcessorInterface
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionAttachmentOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      $id = $uriVariables['id'] ?? null;
      if (!is_string($id)) {
        throw new NotFoundHttpException('Attachment not found.');
      }
      /** @var ResolveInterventionMediaResult $scope */
      $scope = $this->queryBus->ask(new ResolveInterventionMediaQuery(userId: $user->getId(), attachmentId: $id, deletion: true));
      if (null === $scope->attachment) {
        throw new NotFoundHttpException('Attachment not found.');
      }
      $this->revisionGuard->assertMatches($scope->attachment->revision);
      $this->commandBus->dispatch(new DeleteInterventionAttachmentCommand(userId: $user->getId(), interventionId: $scope->interventionId, attachmentId: $scope->attachment->id, expectedRevision: $scope->attachment->revision));

      return null;
    }
    $interventionId = $uriVariables['interventionId'] ?? null;
    if (!is_string($interventionId) || '' === $interventionId) {
      throw new BadRequestHttpException('The interventionId URI parameter is required.');
    }
    $request = $this->currentRequest();
    $clientId = self::validatedClientId($request->request->get('clientId'));
    if (null !== $clientId) {
      /** @var ResolveInterventionMediaResult $replay */
      $replay = $this->queryBus->ask(new ResolveInterventionMediaQuery(userId: $user->getId(), attachmentId: $clientId, interventionId: $interventionId, replay: true));
      if (null !== $replay->attachment) {
        return InterventionMediaProvider::output($replay->attachment);
      }
    }
    $uploaded = $this->attachmentGuard->fromRequest($request);
    $workItem = $request->request->get('workItemId');
    $kind = $request->request->get('kind');
    /** @var AddInterventionAttachmentResult $result */
    $result = $this->commandBus->dispatch(new AddInterventionAttachmentCommand(
      userId: $user->getId(),
      interventionId: $interventionId,
      fileName: $uploaded->fileName,
      contents: $uploaded->contents,
      mimeType: $uploaded->mimeType,
      size: $uploaded->size,
      label: $uploaded->label,
      attachmentId: $clientId,
      workItemId: is_string($workItem) && '' !== $workItem ? ResourceIriParser::id($workItem, 'intervention-work-items') : null,
      kind: is_string($kind) && '' !== $kind ? $kind : 'file',
    ));
    $output = new InterventionAttachmentOutput();
    $output->id = $result->attachmentId;
    $output->interventionId = $result->interventionId;
    $output->fileName = $result->fileName;
    $output->mimeType = $result->mimeType;
    $output->size = $result->size;
    $output->label = $result->label;
    $output->revision = $result->revision;
    $output->uploadedAt = $result->uploadedAt->format('c');
    $output->workItemId = $result->workItemId;
    $output->kind = $result->kind;

    return $output;
  }

  private function currentRequest(): Request
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request instanceof Request) {
      throw new BadRequestHttpException('Request payload is required.');
    }

    return $request;
  }

  private static function validatedClientId(mixed $clientId): ?string
  {
    if (null !== $clientId && !is_string($clientId)) {
      throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.');
    }
    if (null === $clientId || '' === $clientId) {
      return null;
    }

    try {
      return (string) InterventionAttachmentId::fromString($clientId);
    } catch (InvalidValueException $exception) {
      throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.', $exception);
    }
  }
}
