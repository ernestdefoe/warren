<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * The denormalised score and ranking, shared with fof/gamification.
 *
 * A vote row per user is the truth, but no index can sort on it. Both `votes`
 * and `hotness` live on the discussion so Hot and Top are an ORDER BY rather
 * than an aggregate over every vote ever cast.
 *
 * Same column names, same types, same defaults as gamification — so its sorts
 * work on a forum that has only ever used Warren, and Warren's work on a forum
 * that has only ever used gamification.
 *
 * 🚨 `hotness` is copied from gamification's arithmetic, character for
 * character, and NOT from reddit's:
 *
 *     round(log10(max(|score|, 1)) + (sign(score) * seconds) / 45000, 10)
 *
 * Reddit's published version applies the sign to the ORDER term, not to the
 * seconds — `order * sign + seconds / 45000`. Gamification credits reddit in
 * its source but writes the expression above, and on a downvoted discussion
 * the two disagree wildly: reddit nudges it down by a point or so, this drives
 * it thirty years into the past.
 *
 * Warren matches gamification anyway, because the column is shared. Two
 * extensions writing the same column with different maths would leave a
 * forum's front page reordering itself depending on which one last touched a
 * discussion, and "the same as the neighbour" beats "more correct in
 * isolation" for a value neither of us owns alone.
 *
 * Guarded on `votes` alone, exactly as gamification guards it — the two columns
 * are always added together by both, so one is a sufficient sentinel for the
 * pair. No index on `votes` here: gamification adds one in 2021 and an index
 * of the same name cannot be created twice.
 *
 * 🚨 `down` removes NOTHING. Dropping these would blank the score on every
 * discussion for gamification too. See the vote-table migration for the full
 * reasoning; the short version is that disabling a theme must never destroy
 * data another extension is still reading.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('discussions', 'votes')) {
            return;
        }

        $schema->table('discussions', function (Blueprint $table) {
            /*
             * Gamification declares these without defaults. Warren adds them,
             * because a NOT NULL integer with no default is an insert error on
             * a forum in strict mode the moment anything creates a discussion
             * without naming the column. Same name, same type, same width —
             * a default is invisible to a reader and to gamification's guard.
             */
            $table->integer('votes')->default(0);
            $table->float('hotness', 10, 4)->default(0);
        });
    },

    'down' => function (Builder $schema) {
        // Intentionally empty. See the note above.
    },
];
