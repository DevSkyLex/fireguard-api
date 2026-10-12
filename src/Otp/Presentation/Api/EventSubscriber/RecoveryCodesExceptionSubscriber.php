<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\EventSubscriber;

use Otp\Application\Exception\{RecoveryCodesProofRejectedException, TotpEnrollmentNotEnabledException};
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

/**
 * Class RecoveryCodesExceptionSubscriber
 *
 * Maps recovery use-case failures centrally through the existing Problem Details boundary.
 *
 * @category EventSubscriber
 */
final readonly class RecoveryCodesExceptionSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, array{string, int}> HTTP failure callback
   */
  public static function getSubscribedEvents(): array
  {
    return ['kernel.exception' => ['onException', 120]];
  }

  /**
   * Method onException
   *
   * @access public
   *
   * @param ExceptionEvent $event framework-wrapped use-case failure
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void
  {
    $failure = $event->getThrowable();
    while (null !== $failure) {
      $mapped = match (true) {
        $failure instanceof RecoveryCodesProofRejectedException => new UnprocessableEntityHttpException($failure->getMessage(), $failure),
        $failure instanceof TotpEnrollmentNotEnabledException => new NotFoundHttpException('TOTP is not enabled.', $failure),
        $failure instanceof TotpEnrollmentUnavailableException => new AccessDeniedHttpException($failure->getMessage(), $failure),
        default => null,
      };
      if (null !== $mapped) {
        $event->setThrowable($mapped);

        return;
      }
      $failure = $failure->getPrevious();
    }
  }
  // #endregion
}
