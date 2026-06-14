<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function is_array;
use function is_int;
use function json_decode;

/**
 * Subscriber RevisionEtagSubscriber.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class RevisionEtagSubscriber
{
  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param ResponseEvent $event the event value
   */
  public function __invoke(ResponseEvent $event): void
  {
    $response = $event->getResponse();
    if (!$response->isSuccessful() || $response->headers->has('ETag')) {
      return;
    }

    $content = $response->getContent();
    $data = false === $content || '' === $content ? null : json_decode($content, true);
    $revision = is_array($data) ? ($data['revision'] ?? null) : null;
    if (is_int($revision)) {
      $response->setEtag('revision-' . $revision);
    }
  }
}
