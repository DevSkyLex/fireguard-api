<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\EventSubscriber\Idempotency;

use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;

/**
 * Preserves OAuth protocol errors before the generic Problem Details subscriber runs.
 *
 * @category EventSubscriber
 */
final readonly class OAuthIdempotencyExceptionSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string,array{string,int}> protocol exception translation
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 20]];
  }

  /**
   * @param ExceptionEvent $event current OAuth endpoint failure
   */
  public function onException(ExceptionEvent $event): void
  {
    if ($event->hasResponse() || !in_array($event->getRequest()->attributes->get('_api_operation_name', $event->getRequest()->attributes->get('_route')), ['token', 'authorize', 'grant_consent', 'revoke_token'], true)) {
      return;
    }
    $failure = $event->getThrowable();
    $reason = null;
    do {
      if ($failure instanceof IdempotencyDeniedException) {
        $reason = $failure->reason;

        break;
      }
      if ($failure instanceof CommitOutcomeUnknownException) {
        $reason = 'commit_unknown';

        break;
      }
      $failure = $failure->getPrevious();
    } while (null !== $failure);
    if (null === $reason) {
      return;
    }
    $headers = ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache'];
    if ('in_progress' === $reason) {
      $headers['Retry-After'] = '1';
    }
    $body = ['error' => in_array($reason, ['key_required', 'key_invalid', 'input_mismatch'], true) ? 'invalid_request' : 'invalid_grant', 'error_description' => 'This authorization request cannot be safely completed.'];
    if ('expired' === $reason) {
      $body['error_description'] = 'This request was completed, but its replay result has expired.';
      $body['operationCompleted'] = true;
    }
    $event->setResponse(new JsonResponse($body, in_array($reason, ['key_required', 'key_invalid'], true) ? 400 : 409, $headers));
  }
}
