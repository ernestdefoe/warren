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
 * 🚨 `hotness` is the published Reddit algorithm, not an invention of either
 * extension. Gamification credits reddit's own repository in its source, and
 * Warren computes it identically:
 *
 *     round(log10(max(|score|, 1)) + (sign(score) * seconds) / 45000, 10)
 *
 * That matters for interoperability: two extensions writing the same column
 * with different maths would leave a forum's front page reordering itself
 * depending on which one last touched a discussion.
 *
 * Guarded on `votes` alone, exactly as gamification guards it — the two columns
 * are always added together by both, so one is a sufficient sentinel for the
 * pair.
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
            $table->integer('votes')->default(0);
            $table->float('hotness', 10, 4)->default(0);
        });
    },

    'down' => function (Builder $schema) {
        // Intentionally empty. See the note above.
    },
];
