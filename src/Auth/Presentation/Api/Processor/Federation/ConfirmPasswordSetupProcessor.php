<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Service\Federation\InitialPasswordService;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\PasswordChange\ConfirmPasswordChangeInput;
use Auth\Presentation\Api\Dto\Output\PasswordChange\ConfirmPasswordChangeOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function max;
use function time;

/**
 * Class ConfirmPasswordSetupProcessor.
 *
 * Completes the authenticated user's initial password setup after verifying the emailed confirmation code.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<ConfirmPasswordChangeInput, ConfirmPasswordChangeOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordSetupProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the ConfirmPasswordSetupProcessor dependencies and state.
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
    #[Autowire(service: 'limiter.password_setup_confirm')]
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
   * @param ConfirmPasswordChangeInput $data confirmation token, code and new password
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables route variables supplied by API Platform
   * @param array<string, mixed> $context processor context supplied by API Platform
   *
   * @return ConfirmPasswordChangeOutput
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConfirmPasswordChangeOutput
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
      $result = $this->passwordSetup->confirm(
        $user->getId(),
        $data->token ?? '',
        $data->code ?? '',
        $data->newPassword ?? '',
      );

      return new ConfirmPasswordChangeOutput(true, 'Password configured.', attemptsRemaining: $result['attemptsRemaining']);
    } catch (FederatedAuthException $exception) {
      $attempts = 'invalid_code' === $exception->errorCode ? (int) $exception->getMessage() : 0;

      return new ConfirmPasswordChangeOutput(false, 'Password could not be configured.', $exception->errorCode, $attempts);
    }
  }
  // #endregion
}
