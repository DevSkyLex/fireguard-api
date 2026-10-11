<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Subscription;

/**
 * Class MessagingSubscription
 *
 * Carries an opaque subscriber token scoped to one authorized conversation topic.
 *
 * @category Contract
 */
final readonly class MessagingSubscription
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Keeps vendor token factories and claim types behind the subscription port.
   *
   * @access public
   *
   * @param string $token opaque subscriber token
   * @param string $topic exact private conversation topic
   *
   * @return void
   */
  public function __construct(public string $token, public string $topic)
  {
  }
  // #endregion
}
