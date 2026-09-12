<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Api;

use ErnestDefoe\Warren\SharedSchema;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Post\Post;

/**
 * A comment's score and this reader's vote on it.
 *
 * 🚨 READ-ONLY, and registered unconditionally — including on a forum running
 * fof/gamification, whose own fields report the same rows under different
 * names. The write side is the only part that stands down; see
 * PostVoteField.
 */
class PostResourceFields
{
    public function __construct(
        protected SharedSchema $schema
    ) {
    }

    public function __invoke(): array
    {
        $column = $this->schema->voteColumn();

        return [
            /*
             * 🚨 Two counts rather than one sum, and that is not a style
             * choice.
             *
             * `sumRelation` would be the obvious shape and it can only sum a
             * NUMERIC column. The direction is a number (`value`) on a forum
             * that has had gamification and a string ('Up'/'Down') on one that
             * has only run Warren — summing the second is meaningless. Two
             * counted subqueries answer the same question on both shapes with
             * no raw SQL and no branch in the renderer.
             *
             * Counting also costs no eager load: these are subqueries on the
             * post query itself, so a page of fifty comments is still one
             * round trip.
             */
            Schema\Integer::make('warrenUpvotes')
                ->countRelation(
                    'warrenVotes',
                    fn ($query) => $query->where($column, $this->schema->encode(1))
                ),

            Schema\Integer::make('warrenDownvotes')
                ->countRelation(
                    'warrenVotes',
                    fn ($query) => $query->where($column, $this->schema->encode(-1))
                ),

            /*
             * 'up', 'down', or absent.
             *
             * 🚨 Absent for guests rather than null. A null would be pushed
             * into the frontend store and merged over whatever is there; on a
             * page cached between a guest and a member that is how one reader
             * ends up seeing another's votes.
             */
            Schema\Str::make('warrenUserVote')
                ->visible(fn (Post $post, Context $context) => $context->getActor()->exists)
                ->get(fn (Post $post): ?string => $this->loadedVote($post)),

            /*
             * Whether to draw the arrows at all. A control that posts a
             * request the server will refuse is worse than no control.
             */
            Schema\Boolean::make('warrenCanVote')
                ->get(fn (Post $post, Context $context): bool => $context->getActor()->can('warrenVote', $post)),
        ];
    }

    /**
     * 🚨 No query here. A vote control renders on every comment, so a lookup
     * per post is an N+1 on a thread — fifty extra queries to draw fifty
     * arrows. `extend.php` eager-loads `warrenVotes` already constrained to
     * this actor.
     *
     * Constrained by ROW (`where user_id`), never by column; see the
     * discussion fields for why that distinction is load-bearing.
     */
    protected function loadedVote(Post $post): ?string
    {
        if (! $post->relationLoaded('warrenVotes')) {
            return null;
        }

        $vote = $post->warrenVotes->first();

        if ($vote === null) {
            return null;
        }

        return $vote->direction() > 0 ? 'up' : 'down';
    }
}
