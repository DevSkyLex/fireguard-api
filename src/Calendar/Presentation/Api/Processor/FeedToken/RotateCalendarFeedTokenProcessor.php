<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Processor\FeedToken;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken\{RotateCalendarFeedTokenCommand, RotateCalendarFeedTokenResult};
use Calendar\Presentation\Api\Dto\Output\FeedToken\CalendarFeedTokenSecretOutput;
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use DateTimeInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;
use function sprintf;

/**
 * Processor RotateCalendarFeedTokenProcessor.
 *
 * Returns the raw secret exactly once, together with the complete
 * subscribable `.ics` URL (scheme and host taken from the current request).
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, CalendarFeedTokenSecretOutput>
 */
final readonly class RotateCalendarFeedTokenProcessor implements ProcessorInterface
{
  use CalendarExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack, for the feed URL base
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (none — the operation has no body)
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CalendarFeedTokenSecretOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    try {
      /** @var RotateCalendarFeedTokenResult $result */
      $result = $this->commandBus->dispatch(new RotateCalendarFeedTokenCommand(
        organizationId: $organizationId,
        actorUserId: $user->getId(),
      ));
    } catch (Throwable $exception) {
      throw $this->mapCalendarException($exception);
    }

    $request = $this->requestStack->getCurrentRequest();
    $base = null !== $request ? $request->getSchemeAndHttpHost() : '';

    $output = new CalendarFeedTokenSecretOutput();
    $output->secret = $result->secret;
    $output->feedUrl = sprintf('%s/api/calendar/feed/%s.ics', $base, $result->secret);
    $output->createdAt = $result->createdAt->format(DateTimeInterface::ATOM);
    $output->rotated = $result->rotated;

    return $output;
  }
  // #endregion
}
