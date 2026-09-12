<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren;

use Flarum\Discussion\Discussion;

/**
 * The ranking behind "Hot".
 *
 * 🚨 Deliberately identical to fof/gamification's arithmetic, including where
 * that departs from reddit's published algorithm: it applies the sign to the
 * elapsed seconds rather than to the logarithm. On a downvoted discussion the
 * two disagree by decades, not by a point. The column is shared, so agreeing
 * with the neighbour beats being right alone — the full argument is in the
 * discussions migration.
 */
class Hotness
{
    /**
     * Reddit's epoch: 2005-12-08 07:46:43 UTC. Gamification uses the same
     * constant, and a `hotness` value only means anything next to other values
     * measured from the same zero.
     */
    public const EPOCH = 1134028003;

    public function __construct(
        protected SharedSchema $schema
    ) {
    }

    public function for(Discussion $discussion): float
    {
        $score = (int) $discussion->votes;

        $order = log10(max(abs($score), 1));

        $sign = $score <=> 0;

        $seconds = $discussion->created_at->getTimestamp() - self::EPOCH;

        return round($order + (($sign * $seconds) / 45000), 10);
    }

    /**
     * Write the rank to whichever column this forum calls it.
     *
     * Does not save — the caller is already writing `votes` on the same model
     * and there is no reason to touch the row twice.
     */
    public function apply(Discussion $discussion): void
    {
        $discussion->setAttribute($this->schema->rankColumn(), $this->for($discussion));
    }
}
