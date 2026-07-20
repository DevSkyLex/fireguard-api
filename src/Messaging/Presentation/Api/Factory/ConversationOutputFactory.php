<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Factory;

use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;

/**
 * Factory ConversationOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConversationOutputFactory
{
  // #region Constants
  /**
   * Property SUBJECT_RESOURCE_SEGMENTS.
   *
   * Maps a subject type to the descriptive API resource path segment used
   * to build its (illustrative, not necessarily dereferenceable — several
   * subject resources are organization-nested) IRI, mirroring
   * `MaintenanceScheduleOutputFactory`'s treatment of the equipment/facility
   * fields.
   *
   * @var array<string, string>
   */
  private const array SUBJECT_RESOURCE_SEGMENTS = [
    'facility' => 'facilities',
    'equipment' => 'equipment',
    'intervention' => 'interventions',
    'non_conformity' => 'non-conformities',
    'channel' => 'channels',
  ];
  // #endregion

  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param ConversationView $view the conversation view
   * @param ?string $subjectLabel the resolved subject display label, if any
   * @param int $unreadCount the acting member's unread count, if resolved
   * @param bool $isFavorite whether the CURRENT member favorited this conversation (L1.5)
   *
   * @return ConversationOutput the conversation output
   */
  public function fromView(ConversationView $view, ?string $subjectLabel = null, int $unreadCount = 0, bool $isFavorite = false): ConversationOutput
  {
    $output = new ConversationOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->subjectType = $view->subjectType;
    $output->subject = null === $view->subjectId
      ? null
      : '/api/' . self::subjectResourceSegment($view->subjectType) . '/' . $view->subjectId;
    $output->subjectLabel = $subjectLabel;
    $output->visibility = $view->visibility;
    $output->lastMessageAt = $view->lastMessageAt?->format('c');
    $output->messagesCount = $view->messagesCount;
    $output->isArchived = $view->isArchived;
    $output->unreadCount = $unreadCount;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');
    $output->isChannel = 'channel' === $view->subjectType;
    $output->name = $view->name;
    $output->team = null === $view->teamId ? null : '/api/organizations/' . $view->organizationId . '/teams/' . $view->teamId;
    $output->isFavorite = $isFavorite;
    $output->parentConversationId = $view->parentConversationId;

    return $output;
  }

  /**
   * Method subjectResourceSegment.
   *
   * @static
   *
   * Resolves the descriptive API resource path segment for a subject type,
   * shared with the input-side IRI parsing in
   * `GetOrCreateConversationProcessor`.
   *
   * @since 1.0.0
   *
   * @param string $subjectType the subject type value
   *
   * @return string the resource path segment
   */
  public static function subjectResourceSegment(string $subjectType): string
  {
    return self::SUBJECT_RESOURCE_SEGMENTS[$subjectType] ?? $subjectType;
  }
  // #endregion
}
