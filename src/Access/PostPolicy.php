<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Access;

use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class PostPolicy extends AbstractPolicy
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * May this actor vote on this post?
     *
     * Deliberately answered in one place. The arrows ask it to decide whether
     * to render, the writable field asks it to decide whether to accept, and a
     * control that appears but is refused on click is the worst of the three
     * possible outcomes.
     */
    public function warrenVote(User $actor, Post $post): string|bool|null
    {
        if (! $actor->exists) {
            return $this->deny();
        }

        /*
         * Event posts — renames, tag changes, merges — are not opinions and
         * have nothing to upvote. They also have no author, so the self-vote
         * rule below would read `null === null` and match.
         */
        if (! $post instanceof CommentPost) {
            return $this->deny();
        }

        if ($post->hidden_at !== null) {
            return $this->deny();
        }

        /*
         * Voting for yourself. Allowed by default, matching gamification's
         * own default — reddit counts the author's own upvote too — but a
         * forum that treats it as score inflation can switch it off.
         */
        if ((int) $actor->id === (int) $post->user_id
            && ! (bool) $this->settings->get('warren.allow_self_votes', true)) {
            return $this->deny();
        }

        return $actor->hasPermission('warren.vote');
    }
}
