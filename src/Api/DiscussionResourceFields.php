<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Api;

use ErnestDefoe\Warren\SharedSchema;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;

/**
 * The score, the ranking and this reader's own vote.
 *
 * 🚨 These are READ-ONLY and they are registered unconditionally, including on
 * a forum running fof/gamification.
 *
 * That is safe precisely because the schema is shared: `discussions.votes` and
 * the rank column are the same ones gamification maintains, so these fields
 * report the same numbers whoever last wrote them. Only the WRITE side — the
 * vote field and the control that calls it — stands down when gamification is
 * enabled, because two things writing the same row is the one arrangement that
 * would actually conflict.
 *
 * Reading unconditionally is what lets the row layout be the same code on both
 * kinds of forum. A gutter that only knew how to read its own extension's
 * field would need two renderers and they would drift.
 */
class DiscussionResourceFields
{
    public function __construct(
        protected SharedSchema $schema
    ) {
    }

    public function __invoke(): array
    {
        return [
            /*
             * The net score. Denormalised on the discussion rather than summed
             * from `post_votes` because the front page sorts on it — an
             * aggregate over every vote ever cast cannot use an index.
             */
            Schema\Integer::make('warrenScore')
                ->get(fn (Discussion $discussion): int => (int) ($discussion->votes ?? 0)),

            /*
             * The time-decayed ranking behind "Hot". Exposed so the frontend
             * can show why a discussion sits where it does, and so a client
             * sorting locally agrees with the server.
             *
             * 🚨 Read through the schema helper, never as `$discussion->hotness`.
             * Gamification 2.x renames that column to `trending`, so the
             * literal is right on exactly half the forums this will run on.
             */
            Schema\Number::make('warrenRank')
                ->get(fn (Discussion $discussion): float => (float) (
                    $discussion->getAttribute($this->schema->rankColumn()) ?? 0
                )),

            /*
             * 'up', 'down', or absent — what the person reading this has
             * already done, so the arrows render in their active state on
             * first paint rather than after a round trip.
             *
             * 🚨 Absent for guests rather than null. A null would be pushed
             * into the frontend store and merged over whatever is there; on a
             * cached page shared between a guest and a member that is how one
             * reader ends up seeing another's votes. `visible()` false means
             * the key never ships.
             */
            Schema\Str::make('warrenUserVote')
                ->visible(fn (Discussion $discussion, Context $context) => $context->getActor()->exists)
                ->get(fn (Discussion $discussion): ?string => $this->loadedVote($discussion)),
        ];
    }

    /**
     * This actor's vote, read from the relation the index endpoint eager-loaded.
     *
     * 🚨 No query here, on purpose. A gutter renders on every row, so a lookup
     * per discussion is an N+1 on every page of the forum — twenty extra
     * queries to draw twenty arrows. `extend.php` eager-loads `warrenVotes`
     * already constrained to this actor, so by the time a field is serialised
     * the answer is in memory.
     *
     * The relation hangs off the discussion's own `first_post_id` rather than
     * off a loaded `firstPost`, which is why the whole thing costs ONE query
     * and never drags twenty post bodies across just to find out which way an
     * arrow points.
     *
     * It is constrained by ROW (`where user_id`), never by column. Narrowing
     * the columns of a relation other code can ask for is what put a null
     * `createdAt` into Flarum's store and blanked discussion pages in Cascade —
     * see that extension's eager-load comment. A relation Warren defines under
     * its own name cannot collide that way, and it still must not be
     * column-narrowed.
     *
     * Returns null when the relation was never loaded — an endpoint Warren did
     * not extend, or a model built in a console command. "I don't know" is the
     * honest answer there, and it renders as an un-voted gutter rather than as
     * a lie about what this reader did.
     */
    protected function loadedVote(Discussion $discussion): ?string
    {
        if (! $discussion->relationLoaded('warrenVotes')) {
            return null;
        }

        $vote = $discussion->warrenVotes->first();

        if ($vote === null) {
            return null;
        }

        return $vote->direction() > 0 ? 'up' : 'down';
    }
}
