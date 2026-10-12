<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\{GenerateEquipmentExportCommand, GenerateEquipmentExportResult};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentExportTooLargeException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use LogicException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

use function base64_decode;
use function is_string;
use function sprintf;

/**
 * Controller ExportEquipmentsController.
 *
 * Invokable API Platform controller serving
 * `GET /organizations/{organizationId}/equipment/export` (wired via
 * `controller:` on a `Get` operation on `EquipmentExportResource`, with
 * `read`/`write`/`serialize`/`deserialize`/`output` disabled) — mirrors
 * `Intervention\...\ExportInterventionsController`. Kept thin: authenticate,
 * resolve the `organizationId` URI variable exactly like
 * `DownloadEquipmentAttachmentController` does, ask the query bus (the
 * handler enforces the organization scoping/permission check and the
 * export's row cap), stream the CSV, and emit the export's own domain event.
 * Resource-level `is_granted('ROLE_USER')` is only the coarse gate — the true
 * `organization.equipment.read` entitlement and the tenant scoping are
 * resolved inside `ExportEquipmentsHandler`, exactly like every other
 * Equipment endpoint resolves it.
 *
 * @category Controller
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportEquipmentsController extends AbstractController
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commands generates the artifact inside its owning transaction
   * @param Security $security the security service
   */
  public function __construct(
    private readonly CommandBusPort $commands,
    private readonly Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return StreamedResponse the streamed CSV response
   */
  public function __invoke(Request $request): StreamedResponse
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $request->attributes->get('organizationId');
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    try {
      /** @var GenerateEquipmentExportResult $result */
      $result = $this->commands->dispatch(new GenerateEquipmentExportCommand(
        userId: $user->getId(),
        organizationId: $organizationId,
        format: 'csv',
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapExportException($exception);
    }

    $contents = base64_decode($result->contentsBase64, true);
    if (false === $contents) {
      throw new LogicException('Saved CSV artifact is invalid.');
    }
    $response = new StreamedResponse(static function () use ($contents): void {
      echo $contents;
    });
    $response->headers->set('Content-Type', $result->contentType);
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $result->fileName));
    foreach ($result->headers as $name => $value) {
      $response->headers->set($name, $value);
    }

    return $response;
  }

  /**
   * Method mapExportException.
   *
   * Maps the (possibly bus-wrapped) domain exceptions the export handler can
   * throw to their HTTP status, following the same finder-methods idiom
   * `EquipmentExceptionUnwrapperTrait` already offers the rest of the module,
   * rather than the generic recursive-match mapper `Intervention`'s export
   * controller uses.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception raised by the query bus
   *
   * @return Throwable the mapped exception
   */
  private function mapExportException(Throwable $exception): Throwable
  {
    // The finders walk the whole wrapping chain themselves (the exception
    // itself, `getPrevious()`, and Messenger's wrapped exceptions), so no
    // wrapper-type gate is needed — a plain `RuntimeException` wrapper is
    // unwrapped exactly like a `MessengerRuntimeException`, mirroring
    // `Intervention`'s `mapWorkflowException`.
    $notFound = $this->findEquipmentNotFoundException($exception);
    $accessDenied = $this->findEquipmentAccessDeniedException($exception);
    $tooLarge = $this->findEquipmentExportTooLargeException($exception);
    $invalidArgument = $this->findInvalidArgumentException($exception);

    return match (true) {
      $notFound instanceof EquipmentNotFoundException => new NotFoundHttpException($notFound->getMessage(), $exception),
      $accessDenied instanceof EquipmentAccessDeniedException => new AccessDeniedHttpException($accessDenied->getMessage(), $exception),
      $tooLarge instanceof EquipmentExportTooLargeException => new UnprocessableEntityHttpException($tooLarge->getMessage(), $exception),
      $invalidArgument instanceof InvalidArgumentException => new BadRequestHttpException($invalidArgument->getMessage(), $exception),
      default => $exception,
    };
  }
  // #endregion
}
