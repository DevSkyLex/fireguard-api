<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Controller;

use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\{GetCalendarFeedQuery, GetCalendarFeedResult};
use Calendar\Application\UseCase\Query\FeedToken\ResolveCalendarFeedToken\{ResolveCalendarFeedTokenQuery, ResolveCalendarFeedTokenResult};
use Calendar\Presentation\Api\Ical\CalendarFeedIcalWriter;
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use DateTimeImmutable;
use DateTimeZone;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function is_string;

/**
 * Controller GetCalendarFeedIcsController.
 *
 * The unauthenticated iCal subscription endpoint
 * (`GET /api/calendar/feed/{token}.ics`, PUBLIC_ACCESS in
 * `config/packages/security.yaml`, same mechanism as the public invitation
 * preview). The capability IS the URL: the token secret authenticates the
 * request. Thin composition of two use cases — resolve the token (hash
 * lookup, hour-throttled `lastUsedAt`, window computation), then the exact
 * same `GetCalendarFeedQuery` the interactive feed runs, with the token
 * member's identity, so the member's `organization.events.read` permission
 * and visibility rules apply unchanged.
 *
 * Anti-oracle: an unknown token, a revoked token, and a token whose member
 * lost the read permission all answer the same plain 404 — an outsider
 * probing URLs learns nothing about which case they hit.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetCalendarFeedIcsController extends AbstractController
{
  use CalendarExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param CalendarFeedIcalWriter $icalWriter the RFC 5545 serializer
   * @param RateLimiterFactory|null $rateLimiter the per-IP feed rate limiter
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly CalendarFeedIcalWriter $icalWriter,
    #[Autowire(service: 'limiter.calendar_feed')]
    private readonly ?RateLimiterFactory $rateLimiter = null,
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
   * @return Response the `text/calendar` feed response
   */
  public function __invoke(Request $request): Response
  {
    $this->enforceRateLimit($request);

    $token = $request->attributes->get('token');
    if (!is_string($token) || '' === $token) {
      throw new NotFoundHttpException('Calendar feed token not found.');
    }

    try {
      /** @var ResolveCalendarFeedTokenResult $resolved */
      $resolved = $this->queryBus->ask(new ResolveCalendarFeedTokenQuery(secret: $token));

      /** @var GetCalendarFeedResult $feed */
      $feed = $this->queryBus->ask(new GetCalendarFeedQuery(
        userId: $resolved->userId,
        organizationId: $resolved->organizationId,
        from: $resolved->from,
        to: $resolved->to,
      ));
    } catch (Throwable $exception) {
      throw $this->mapToUniformNotFound($exception);
    }

    $document = $this->icalWriter->write(
      items: $feed->items,
      organizationId: $resolved->organizationId,
      generatedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
    );

    return new Response($document, Response::HTTP_OK, [
      'Content-Type' => 'text/calendar; charset=utf-8',
      // The body is member-private and reached through a capability URL:
      // never shared-cacheable, and only briefly client-cacheable.
      'Cache-Control' => 'private, max-age=300',
      'X-Robots-Tag' => 'noindex',
    ]);
  }

  /**
   * Method enforceRateLimit.
   *
   * Throttles the public feed endpoint per client IP to deter token
   * enumeration, mirroring the public invitation preview; a missing limiter
   * (e.g. some test contexts) is a no-op.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   */
  private function enforceRateLimit(Request $request): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $clientIp = $request->getClientIp() ?? '127.0.0.1';
    if (!$this->rateLimiter->create($clientIp)->consume()->isAccepted()) {
      throw new TooManyRequestsHttpException(message: 'Too many calendar feed requests.');
    }
  }

  /**
   * Method mapToUniformNotFound.
   *
   * Maps every failure of the resolve-then-feed pipeline to the same 404:
   * unknown token, revoked token, and a member who lost the feed read
   * permission are deliberately indistinguishable from outside. Any other
   * exception is rethrown untouched (a 5xx must stay a 5xx).
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the raw exception, possibly bus-wrapped
   *
   * @return Throwable the mapped exception
   */
  private function mapToUniformNotFound(Throwable $exception): Throwable
  {
    $mapped = $this->mapCalendarException($exception);

    // Both the token miss (already a 404) and a member who lost the feed
    // read permission (a 403 through the module's shared mapper) collapse
    // into the same uniform 404 with a constant message.
    if ($mapped instanceof NotFoundHttpException || $mapped instanceof AccessDeniedHttpException) {
      return new NotFoundHttpException('Calendar feed token not found.', $exception);
    }

    return $mapped;
  }
  // #endregion
}
