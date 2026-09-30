<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Service\Federation\InitialPasswordService;
use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Output\PasswordChange\RequestPasswordChangeOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function max;
use function time;

/**
 * Class RequestPasswordSetupProcessor.
 *
 * Starts password setup for the authenticated user and returns the challenge delivery details.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<void, RequestPasswordChangeOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordSetupProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the RequestPasswordSetupProcessor dependencies and state.
   *
   * @access public
   *
   * @param InitialPasswordService $passwordSetup the password setup
   * @param Security $security the security
   * @param RateLimiterFactory $rateLimiter the rate limiter
   *
   * @return void
   */
  public function __construct(
    private InitialPasswordService $passwordSetup,
    private Security $security,
    #[Autowire(service: 'limiter.password_setup')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method process
   *
   * Processes the API operation by translating the request into the corresponding application action.
   *
   * @access public
   *
   * @param mixed $data unused because this operation has no request body
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables route variables supplied by API Platform
   * @param array<string, mixed> $context processor context supplied by API Platform
   *
   * @return RequestPasswordChangeOutput
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RequestPasswordChangeOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $limit = $this->rateLimiter->create($user->getId())->consume();
    if (!$limit->isAccepted()) {
      throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()));
    }

    try {
      $challenge = $this->passwordSetup->request($user->getId());
    } catch (FederatedConflictException $exception) {
      throw new ConflictHttpException($exception->errorCode, $exception);
    } catch (FederatedUnauthorizedException $exception) {
      throw new UnauthorizedHttpException('Bearer', $exception->errorCode, $exception);
    } catch (FederatedAuthException $exception) {
      throw new BadRequestHttpException($exception->errorCode, $exception);
    }

    return new RequestPasswordChangeOutput(
      success: true,
      message: 'A verification code has been sent.',
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
    );
  }
  // #endregion
}
