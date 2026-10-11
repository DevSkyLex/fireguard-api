<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\EventSubscriber;

use Messaging\Presentation\Api\Service\MessagingExceptionMapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function preg_match;

/**
 * Class MessagingExceptionSubscriber
 *
 * Maps uncaught use-case failures before API Platform normalizes the response.
 * Existing local translators delegate to the same mapper during migration.
 *
 * @category EventSubscriber
 */
final readonly class MessagingExceptionSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Shares the single Messaging HTTP error policy with the transport translators.
   *
   * @access public
   *
   * @param MessagingExceptionMapper $mapper module HTTP error mapper
   *
   * @return void
   */
  public function __construct(private MessagingExceptionMapper $mapper)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Runs before the API Platform exception renderer.
   *
   * @access public
   *
   * @return array<string, array{string, int}> subscribed kernel events
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Method onException
   *
   * Preserves existing HTTP failures and confines generic argument mapping to Messaging routes.
   *
   * @access public
   *
   * @param ExceptionEvent $event failing Messaging HTTP request
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void
  {
    $failure = $event->getThrowable();
    if ($failure instanceof HttpExceptionInterface
      || 1 !== preg_match('#^/api/(?:messages|conversations|messaging-attachments|channels|direct-conversations|presence)(?:/|$)#', $event->getRequest()->getPathInfo())) {
      return;
    }
    $event->setThrowable($this->mapper->map($failure));
  }
  // #endregion
}
