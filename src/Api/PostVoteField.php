<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Api;

use ErnestDefoe\Warren\Voting\VoteManager;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Post\Post;

/**
 * The write side of voting.
 *
 * 🚨 Registered ONLY when fof/gamification is disabled — see extend.php. Two
 * extensions reading the same rows is the entire design; two extensions
 * writing them, each recomputing the same denormalised totals from its own
 * listeners, is how a score ends up disagreeing with the votes underneath it.
 *
 * A vote is a PATCH of the post it belongs to rather than a route of its own.
 * That keeps it inside the policy the rest of the resource already answers to,
 * and the response carries the post back with its new counts, so the client
 * has nothing to re-fetch.
 */
class PostVoteField
{
    public function __construct(
        protected VoteManager $votes
    ) {
    }

    public function __invoke(): array
    {
        return [
            /*
             * 🚨 `hidden()` — it is written, never read back.
             *
             * What the actor voted is served as `warrenUserVote`, loaded in
             * bulk for the whole page. A readable field here would be a
             * second source for the same fact, and the two would answer
             * differently the moment one was loaded and the other was not.
             */
            Schema\Str::make('warrenVote')
                ->hidden()
                ->writable(fn (Post $post, Context $context) => $context->updating()
                    && $context->getActor()->can('warrenVote', $post))
                ->in(['up', 'down'])
                ->nullable()
                ->set(function (Post $post, ?string $value, Context $context) {
                    $this->votes->vote($post, $context->getActor(), match ($value) {
                        'up' => 1,
                        'down' => -1,
                        default => null,
                    });
                }),
        ];
    }
}
