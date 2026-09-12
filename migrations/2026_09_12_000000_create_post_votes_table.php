<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * The vote table, deliberately shared with fof/gamification.
 *
 * Warren does not keep its own votes. It reads and writes `post_votes`, the
 * table fof/gamification has used since 2019, so a forum can start on Warren's
 * voting and install gamification later — or the reverse — and every vote ever
 * cast is still there. Nothing to export, nothing to reconcile, no import
 * command. That is the whole reason this file looks like somebody else's.
 *
 * 🚨 EXACTLY gamification's 2019 shape: id, post_id, user_id, type. Not the
 * shape gamification runs on TODAY, and that is the deliberate part.
 *
 * Gamification replaced `type` with an integer `value` in 2020, converting
 * every row and dropping the old column. Creating the modern shape here would
 * be the obvious move and it is the wrong one: gamification's migrations are
 * tracked per extension, so installing it later replays the WHOLE chain, and
 * its 2020 migration adds `value` unguarded. On a table that already had one,
 * that is a duplicate-column error — enabling gamification would fail outright
 * on any forum that had run Warren first.
 *
 * The 2019 shape is the only one the chain replays cleanly from. Every later
 * gamification migration then does exactly what it was written to do, on
 * Warren's rows: add `value`, convert them, drop `type`, add timestamps, add
 * the foreign keys, add the unique index. A forum that switches ends up with a
 * table indistinguishable from one gamification built itself.
 *
 * Which is why none of those are here. Timestamps in particular: gamification
 * adds them with Flarum's `Migration::addColumns`, which does NOT check whether
 * a column exists — it calls `addColumn` unconditionally.
 *
 * The cost is that a Warren-only forum has no unique index on
 * (post_id, user_id), so uniqueness is enforced in code instead — which the
 * voting UI has to do anyway, because a vote is a toggle rather than an insert.
 *
 * 🚨 `down` removes NOTHING, and that asymmetry is the point.
 *
 * The instinct when writing a migration is to make `down` mirror `up`. Here
 * that would mean dropping a table that fof/gamification may be the only thing
 * still using — disabling a THEME would silently delete a forum's entire voting
 * history. Warren creates this table if it is absent and never takes it away.
 *
 * (Gamification's own `down` does drop it. That is their call and outside our
 * reach; it is documented in the README so nobody is surprised by it.)
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('post_votes')) {
            return;
        }

        $schema->create('post_votes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('post_id')->unsigned();
            $table->integer('user_id')->unsigned();
            /*
             * 🚨 The values are 'Up' and 'Down', capitalised. See SharedSchema.
             *
             * Gamification's converter tests `$vote->type === 'Up'` exactly.
             * Anything it does not recognise maps to 0, and a row that maps to
             * 0 is DELETED rather than kept — so lowercase here would mean
             * every vote cast through Warren silently vanished on the day
             * someone installed gamification. That is the exact outcome this
             * whole shared-table design exists to prevent, undone by two
             * characters.
             */
            $table->string('type');

            /*
             * Not gamification's — it has no plain index here, only the unique
             * one it adds in 2022, and that one is named differently
             * (`..._unique` vs `..._index`), so both can exist and the later
             * migration still succeeds.
             *
             * Warren needs it because the discussion list asks
             * `post_id IN (…) AND user_id = ?` on every page load. Without an
             * index that is a full scan of every vote on the forum to draw
             * twenty arrows.
             */
            $table->index(['post_id', 'user_id']);
        });
    },

    'down' => function (Builder $schema) {
        // Intentionally empty. See the note above.
    },
];
