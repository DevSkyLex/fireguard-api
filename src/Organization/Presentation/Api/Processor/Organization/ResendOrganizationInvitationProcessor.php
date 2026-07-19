<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation\{ResendOrganizationInvitationCommand, ResendOrganizationInvitationResult};
use Organization\Domain\Exception\{OrganizationInvitationNotFoundException, OrganizationInvitationNotificationFailedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Organization\Presentation\Api\Trait\InvitationOutputMapperTrait;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, HttpException, NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function is_string;

/**
 * Processor ResendOrganizationInvitationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, OrganizationInvitationOutput>
 */
final readonly class ResendOrganizationInvitationProcessor implements ProcessorInterface
{
  use InvitationOutputMapperTrait;
  use MessengerExceptionUnwrapperTrait;

  private const int HTTP_BAD_GATEWAY = 502;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ResendOrganizationInvitationProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   * @param RateLimiterFactory|null $rateLimiter the per-user invitation resend rate limiter
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    #[Autowire(service: 'limiter.invitation_resend')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes API input and dispatches the corresponding command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationInvitationOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $invitationId = $uriVariables['invitationId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!is_string($invitationId) || '' === $invitationId) {
      throw new BadRequestHttpException('InvitationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing Organization.members.manage permission.');
    }

    $this->enforceRateLimit($user->getId());

    try {
      /** @var ResendOrganizationInvitationResult $result */
      $result = $this->commandBus->dispatch(new ResendOrganizationInvitationCommand(
        organizationId: $organizationId,
        invitationId: $invitationId,
        resentByUserId: $user->getId(),
      ));
    } catch (OrganizationInvitationNotFoundException|OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (OrganizationInvitationNotificationFailedException $exception) {
      throw new HttpException(self::HTTP_BAD_GATEWAY, $exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, OrganizationInvitationNotFoundException::class)
        ?? $this->findException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $notificationFailed = $this->findException($exception, OrganizationInvitationNotificationFailedException::class);
      if (null !== $notificationFailed) {
        throw new HttpException(self::HTTP_BAD_GATEWAY, $notificationFailed->getMessage(), $exception);
      }

      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if (null !== $invalidArgument) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->buildInvitationOutput($result);
  }

  /**
   * Method enforceRateLimit.
   *
   * Throttles invitation resends per authenticated user to deter inbox
   * flooding and repeated token rotation; a missing limiter (e.g. some test
   * contexts) is a no-op.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   */
  private function enforceRateLimit(string $userId): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    if (!$this->rateLimiter->create($userId)->consume()->isAccepted()) {
      throw new TooManyRequestsHttpException(message: 'Too many invitation resend requests.');
    }
  }
  // #endregion
}
