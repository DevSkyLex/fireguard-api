<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Presence;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeInterface;
use Messaging\Application\UseCase\Command\Presence\PingPresence\{PingPresenceCommand, PingPresenceResult};
use Messaging\Presentation\Api\Dto\Input\PingPresenceInput;
use Messaging\Presentation\Api\Dto\Output\PingPresenceOutput;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function max;
use function sprintf;
use function time;

/**
 * Processor PingPresenceProcessor.
 *
 * Handles `POST /api/presence/ping` (L2.7): records the acting member's
 * online presence with a 90s TTL — no database write, see
 * `PingPresenceHandler`. Rate-limited per authenticated user + organization
 * (unlike the IP-keyed anonymous rate limiters elsewhere in this codebase,
 * this endpoint is always authenticated, so the user id is already a
 * stable, opaque identity — no IP/hashing needed): an unthrottled presence
 * ping would otherwise be a free DoS on the cache.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<PingPresenceInput, PingPresenceOutput>
 */
final readonly class PingPresenceProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param Security $security the security value
   * @param ?RateLimiterFactory $rateLimiter the presence ping rate limiter, when configured
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    #[Autowire(service: 'limiter.messaging_presence_ping')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }

  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return PingPresenceOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PingPresenceOutput
  {
    $user = $this->user();

    if (!$data instanceof PingPresenceInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    $organizationId = ResourceIriParser::id($data->organization, 'organizations');

    $this->enforceRateLimit($user->getId(), $organizationId);

    try {
      /** @var PingPresenceResult $result */
      $result = $this->commandBus->dispatch(new PingPresenceCommand($user->getId(), $organizationId));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    $output = new PingPresenceOutput();
    $output->memberId = $result->memberId;
    $output->lastSeenAt = $result->lastSeenAt->format(DateTimeInterface::ATOM);

    return $output;
  }

  /**
   * Method enforceRateLimit.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the organization identifier
   */
  private function enforceRateLimit(string $userId, string $organizationId): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($this->rateLimitKey($userId, $organizationId))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many presence pings. Please try again in %d seconds.', $seconds),
    );
  }

  /**
   * Method rateLimitKey.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the organization identifier
   *
   * @return string the rate limiter key
   */
  private function rateLimitKey(string $userId, string $organizationId): string
  {
    return sprintf('messaging_presence_ping_%s_%s', $userId, $organizationId);
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
