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
 * 🚨 EXACTLY the base shape: id, post_id, user_id, type. Nothing else.
 *
 * Gamification adds `created_at`/`updated_at` in a later migration of its own,
 * and it does so with Flarum's `Migration::addColumns`, which does NOT check
 * whether a column already exists — it calls `addColumn` unconditionally. So if
 * this table were created with timestamps already on it, installing
 * gamification afterwards would fail on a duplicate column and the admin would
 * be left with a half-migrated extension. Their foreign keys and their unique
 * index are omitted for the same reason.
 *
 * The cost is that a Warren-only forum has no unique index on
 * (user_id, post_id), so uniqueness is enforced in code instead — which the
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
            // 'up' or 'down' — matching gamification's stored values exactly,
            // because the whole point is that both read the same rows.
            $table->string('type');
        });
    },

    'down' => function (Builder $schema) {
        // Intentionally empty. See the note above.
    },
];
