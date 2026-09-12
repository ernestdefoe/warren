<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Voting;

use ErnestDefoe\Warren\Hotness;
use ErnestDefoe\Warren\SharedSchema;
use ErnestDefoe\Warren\Vote;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Casting, changing and clearing a vote, and keeping the denormalised totals
 * that follow from it honest.
 *
 * Three things are written for one click, and the whole point of doing it here
 * rather than in a controller is that they either all happen or none do:
 * the vote row, the discussion's score and rank, and the author's point total
 * where the forum keeps one.
 */
class VoteManager
{
    public function __construct(
        protected ConnectionInterface $db,
        protected SharedSchema $schema,
        protected Hotness $hotness
    ) {
    }

    /**
     * @param int|null $direction 1, -1, or null to clear
     */
    public function vote(Post $post, User $actor, ?int $direction): ?Vote
    {
        return $this->db->transaction(function () use ($post, $actor, $direction) {
            /*
             * 🚨 Locked, not just fetched.
             *
             * A Warren-only forum has no unique index on (post_id, user_id) —
             * it cannot have one, because gamification adds that index itself
             * in a later migration and an index cannot be created twice. So
             * uniqueness is this SELECT's job. Without the lock, two clicks
             * arriving together both find nothing and both insert, and the
             * post carries a permanent double vote from one person that
             * nothing in the UI can undo.
             */
            $existing = Vote::query()
                ->where('post_id', $post->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            $vote = null;

            // Clicking the arrow you already chose clears the vote, the way
            // every site with this control behaves.
            if ($direction === null || ($existing && $existing->direction() === $direction)) {
                $existing?->delete();
            } else {
                $vote = $existing ?: new Vote([
                    'post_id' => $post->id,
                    'user_id' => $actor->id,
                ]);

                $vote->setDirection($direction)->save();
            }

            $this->refresh($post);

            return $vote;
        });
    }

    /**
     * Recompute everything derived from this post's votes.
     */
    public function refresh(Post $post): void
    {
        $discussion = $post->discussion;

        /*
         * Only the first post's votes are the discussion's score — that is
         * gamification's rule, not a simplification. Comment votes are summed
         * on demand for the comment itself; rolling them into the discussion
         * would mean a busy thread outranking a well-received one.
         */
        if ($discussion && (int) $discussion->first_post_id === (int) $post->id) {
            $this->refreshDiscussion($discussion);
        }

        if ($post->user_id && $this->schema->tracksUserPoints()) {
            $this->refreshUserPoints((int) $post->user_id);
        }
    }

    public function refreshDiscussion(Discussion $discussion): void
    {
        $discussion->votes = $this->scoreFor((int) $discussion->first_post_id);

        $this->hotness->apply($discussion);

        $discussion->save();
    }

    /** The net score of one post, summed from the vote rows. */
    public function scoreFor(int $postId): int
    {
        $row = $this->db->table('post_votes')
            ->where('post_id', $postId)
            ->selectRaw($this->schema->sumExpression().' as warren_score')
            ->first();

        return (int) ($row->warren_score ?? 0);
    }

    /**
     * Gamification's per-user point total: the sum of every vote on every post
     * this person wrote.
     *
     * Warren keeps it current only when the column exists, and never creates
     * it. The happy consequence is that a forum which adds gamification later
     * finds correct totals waiting — it recomputes from the same full history,
     * so nothing has to be backfilled.
     *
     * 🚨 The join is aliased. `posts` has a `type` column of its own, so on a
     * table still in the pre-2020 shape an unqualified `type` is ambiguous and
     * the query errors; and a qualifier written as a literal table name would
     * miss the prefix on a prefixed forum, because raw SQL is passed through
     * verbatim. An alias the builder did not prefix is safe on both counts.
     */
    protected function refreshUserPoints(int $userId): void
    {
        $row = $this->db->table('post_votes as wv')
            ->join('posts as wp', 'wp.id', '=', 'wv.post_id')
            ->where('wp.user_id', $userId)
            ->selectRaw($this->schema->sumExpression('wv').' as warren_score')
            ->first();

        $this->db->table('users')
            ->where('id', $userId)
            ->update(['votes' => (int) ($row->warren_score ?? 0)]);
    }
}
