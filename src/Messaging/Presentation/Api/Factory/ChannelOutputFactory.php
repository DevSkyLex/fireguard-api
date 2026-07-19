<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Factory;

use Messaging\Application\Contract\Channel\{ChannelView, ParticipantView};
use Messaging\Presentation\Api\Dto\Output\{ChannelOutput, ChannelParticipantOutput};

/**
 * Factory ChannelOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChannelOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param ChannelView $view the channel view
   * @param int $unreadCount the acting member's unread count, if resolved
   * @param bool $isFavorite whether the CURRENT member favorited this channel (L1.5)
   *
   * @return ChannelOutput the channel output
   */
  public function fromView(ChannelView $view, int $unreadCount = 0, bool $isFavorite = false): ChannelOutput
  {
    $output = new ChannelOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->name = $view->name;
    $output->team = null === $view->teamId ? null : '/api/organizations/' . $view->organizationId . '/teams/' . $view->teamId;
    $output->createdByMember = null === $view->createdByMemberId ? null : '/api/organizations/' . $view->organizationId . '/members/' . $view->createdByMemberId;
    $output->participantCount = $view->participantCount;
    $output->isArchived = $view->isArchived;
    $output->lastMessageAt = $view->lastMessageAt?->format('c');
    $output->messagesCount = $view->messagesCount;
    $output->unreadCount = $unreadCount;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');
    $output->isFavorite = $isFavorite;
    $output->parent = null === $view->parentChannelId ? null : '/api/channels/' . $view->parentChannelId;

    return $output;
  }

  /**
   * Method participantFromView.
   *
   * @since 1.0.0
   *
   * @param ParticipantView $view the participant view
   *
   * @return ChannelParticipantOutput the participant output
   */
  public function participantFromView(ParticipantView $view): ChannelParticipantOutput
  {
    $output = new ChannelParticipantOutput();
    $output->memberId = $view->memberId;
    $output->role = $view->role;
    $output->source = $view->source;
    $output->addedAt = $view->addedAt->format('c');

    return $output;
  }
  // #endregion
}
