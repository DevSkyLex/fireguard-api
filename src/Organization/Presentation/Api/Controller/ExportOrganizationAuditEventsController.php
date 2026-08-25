<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Controller;

use Audit\Application\Contract\AuditExportTooLargeException;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Query\Organization\ExportOrganizationAuditEvents\{ExportOrganizationAuditEventsQuery, ExportOrganizationAuditEventsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Presentation\Api\Service\OrganizationAuditEventCsvWriter;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

use function fclose;
use function fopen;
use function is_string;
use function sprintf;

/**
 * Controller ExportOrganizationAuditEventsController.
 *
 * Streams one organization's audit ledger as CSV.
 *
 * The scoping is taken from the URI and passed to the use case, never composed
 * from query parameters. The platform export does the opposite — it builds its
 * criteria from the request — which is precisely why that one is reserved to
 * holders of the platform `audit.export` permission and this one is not.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportOrganizationAuditEventsController
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constants
  /**
   * The single message both "no such organization" and "not a member" answer
   * with, so an outsider cannot tell the two apart.
   *
   * @since 1.0.0
   */
  private const string NOT_FOUND_MESSAGE = 'Organization not found.';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param OrganizationAuditEventCsvWriter $csvWriter the organization-scoped CSV writer
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private OrganizationAuditEventCsvWriter $csvWriter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param Request $request the current request
   * @param string $organizationId the organization identifier from the URI
   *
   * @return StreamedResponse the CSV download
   */
  public function __invoke(Request $request, string $organizationId): StreamedResponse
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    if ('' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    try {
      /** @var ExportOrganizationAuditEventsResult $result */
      $result = $this->queryBus->ask(new ExportOrganizationAuditEventsQuery(
        organizationId: $organizationId,
        userId: $user->getId(),
        action: $this->stringOrNull($request->query->get('action')),
        from: $this->parseDate($request, 'from'),
        to: $this->parseDate($request, 'to'),
      ));
    } catch (Throwable $exception) {
      throw $this->toHttpException($exception);
    }

    $fileName = sprintf(
      'organization-audit-export-%s.csv',
      new DateTimeImmutable()->format('Ymd-His'),
    );

    $response = new StreamedResponse(function () use ($result): void {
      $handle = fopen('php://output', 'wb');
      if (false === $handle) {
        return;
      }

      $this->csvWriter->write($result->rows, $handle);
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }

  /**
   * Method toHttpException.
   *
   * Maps a use-case failure to its HTTP answer, unwrapping the bus envelopes
   * first — a direct catch never matches, because the adapters wrap every
   * handler failure in `MessengerRuntimeException`.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception as caught
   *
   * @return Throwable the exception to throw instead
   */
  private function toHttpException(Throwable $exception): Throwable
  {
    $tooLarge = $exception instanceof AuditExportTooLargeException
      ? $exception
      : $this->findWrappedException($exception, AuditExportTooLargeException::class);
    if ($tooLarge instanceof AuditExportTooLargeException) {
      return new UnprocessableEntityHttpException($tooLarge->getMessage(), $exception);
    }

    $accessDenied = $exception instanceof OrganizationAccessDeniedException
      ? $exception
      : $this->findWrappedException($exception, OrganizationAccessDeniedException::class);
    if ($accessDenied instanceof OrganizationAccessDeniedException) {
      return new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
    }

    if (
      $exception instanceof OrganizationNotFoundException
      || $exception instanceof OrganizationMemberNotFoundException
      || null !== $this->findWrappedException($exception, OrganizationNotFoundException::class)
      || null !== $this->findWrappedException($exception, OrganizationMemberNotFoundException::class)
    ) {
      return new NotFoundHttpException(self::NOT_FOUND_MESSAGE, $exception);
    }

    return $exception;
  }

  /**
   * Method parseDate.
   *
   * @since 1.0.0
   *
   * @param Request $request the current request
   * @param string $key the query parameter name
   *
   * @return DateTimeImmutable|null the parsed bound, or null when absent
   */
  private function parseDate(Request $request, string $key): ?DateTimeImmutable
  {
    $raw = $this->stringOrNull($request->query->get($key));
    if (null === $raw) {
      return null;
    }

    try {
      return new DateTimeImmutable($raw);
    } catch (Throwable $exception) {
      throw new BadRequestHttpException(sprintf('Invalid "%s" datetime filter.', $key), $exception);
    }
  }

  /**
   * Method stringOrNull.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw query value
   *
   * @return string|null the trimmed value, or null when empty
   */
  private function stringOrNull(mixed $value): ?string
  {
    return is_string($value) && '' !== $value ? $value : null;
  }
  // #endregion
}
