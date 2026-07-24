<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead\{MarkAllNotificationsAsReadCommand, MarkAllNotificationsAsReadResult};
use Notification\Presentation\Api\Dto\Output\Notification\MarkAllNotificationsAsReadOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;
use function trim;

/**
 * Processor MarkAllNotificationsAsReadProcessor.
 *
 * @category Processor
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, MarkAllNotificationsAsReadOutput>
 */
final readonly class MarkAllNotificationsAsReadProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack (for the optional `organization` filter)
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
   * @since 1.1.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context processor context
   *
   * @return MarkAllNotificationsAsReadOutput the mark-all-as-read output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MarkAllNotificationsAsReadOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $this->toNullableString($this->requestStack->getCurrentRequest()?->query->get('organization'));

    /** @var MarkAllNotificationsAsReadResult $result */
    $result = $this->commandBus->dispatch(new MarkAllNotificationsAsReadCommand(
      userId: $user->getId(),
      organizationId: $organizationId,
    ));

    $output = new MarkAllNotificationsAsReadOutput();
    $output->count = $result->count;

    return $output;
  }

  /**
   * Method toNullableString.
   *
   * @since 1.1.0
   *
   * @param mixed $value the raw filter value
   *
   * @return string|null trimmed non-empty string, or null
   */
  private function toNullableString(mixed $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }

    $trimmed = trim($value);

    return '' !== $trimmed ? $trimmed : null;
  }
  // #endregion
}
