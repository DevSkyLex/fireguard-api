<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\EventSubscriber;

use Organization\Domain\Exception\{OrganizationJoinException, OrganizationJoinInputException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds a stable client error code after shared bus unwrapping, without exposing internals.
 *
 * @category EventSubscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinFailureSubscriber implements EventSubscriberInterface
{
  /**
   * @since 1.0.0
   *
   * @param TranslatorInterface $translator localized user-facing errors
   */
  public function __construct(private TranslatorInterface $translator)
  {
  }

  /**
   * @since 1.0.0
   *
   * @return array<string,array{string,int}> subscribed events
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * @since 1.0.0
   *
   * @param ExceptionEvent $event unwrapped domain failure
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    if (!$error instanceof OrganizationJoinException && !$error instanceof OrganizationJoinInputException) {
      return;
    }
    $status = $error instanceof OrganizationJoinInputException ? 422 : 409;
    $event->setResponse(new JsonResponse(['type' => 'about:blank', 'title' => 'Organization access request failed', 'status' => $status, 'code' => $error->getMessage(), 'detail' => $this->translator->trans('request_failed', [], 'organization_join')], $status, ['Content-Type' => 'application/problem+json']));
  }
}
