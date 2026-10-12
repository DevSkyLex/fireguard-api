<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Controller;

use Calendar\Application\UseCase\Command\Feed\ReadCalendarFeedIcs\{ReadCalendarFeedIcsCommand, ReadCalendarFeedIcsResult};
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
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
 * The subscription secret authenticates the member-scoped feed. Its owning
 * command atomically commits hourly usage and the exact serialized response.
 * The declared Idempotency-Key selects a poll execution; a fresh key fetches
 * current content, while a verified replay preserves the original bytes.
 *
 * Anti-oracle: an unknown/revoked token, an inactive account, a removed
 * membership and a lost read permission all answer the same plain 404 — an outsider
 * probing URLs learns nothing about which case they hit.
 *
 * @category Controller
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
   * @param CommandBusPort $commandBus the owning command bus
   * @param RateLimiterFactory|null $rateLimiter the per-IP feed rate limiter
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
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
      /** @var ReadCalendarFeedIcsResult $result */
      $result = $this->commandBus->dispatch(new ReadCalendarFeedIcsCommand($token));
    } catch (Throwable $exception) {
      throw $this->mapToUniformNotFound($exception);
    }

    $headers = ['Content-Type' => $result->contentType, 'Cache-Control' => $result->cacheControl, 'X-Robots-Tag' => 'noindex'];
    if (null !== $result->retryAfter) {
      $headers['Retry-After'] = $result->retryAfter;
    }

    return new Response($result->document, $result->statusCode, $headers);
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
