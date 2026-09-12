<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Api;

use ErnestDefoe\Warren\SharedSchema;
use Flarum\Api\Schema;
use Flarum\Extension\ExtensionManager;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

/**
 * What the frontend needs to know before it draws a single arrow.
 */
class ForumResourceFields
{
    /**
     * Long enough that the counts cost nothing on a busy forum, short enough
     * that a new member sees the number move the same day they joined.
     */
    private const COUNT_TTL = 300;

    public function __construct(
        protected ExtensionManager $extensions,
        protected ConnectionInterface $db,
        protected Cache $cache,
        protected SharedSchema $schema
    ) {
    }

    public function __invoke(): array
    {
        return [
            /*
             * 🚨 The gutter is ONE control with two backends, and this is what
             * tells it which one it is talking to.
             *
             * Warren's write side stands down when fof/gamification is enabled
             * — two extensions recomputing the same denormalised totals from
             * their own listeners is how a score ends up disagreeing with the
             * votes underneath it. But standing the write side down is not the
             * same as standing the THEME down: a Reddit layout with somebody
             * else's thumb buttons in the middle of it is two themes.
             *
             * So the control stays Warren's and the endpoint changes
             * underneath it. Sent rather than inferred, because the frontend
             * cannot see which extensions are enabled and a wrong guess is a
             * control that silently does nothing.
             */
            Schema\Str::make('warrenVoteField')
                ->get(fn (): string => $this->extensions->isEnabled('fof-gamification')
                    ? 'vote'
                    : 'warrenVote'),

            /*
             * The About card's figures.
             *
             * 🚨 Warren serialises its own rather than reading somebody's.
             * Core publishes no counts on the forum resource, and the ones
             * that are there on a given forum belong to whichever other theme
             * happens to be installed — `respawnPostCount`, `mosaicUserCount`.
             * Reading one of those would make the card work on the dev forum
             * and show zeros everywhere else, which is the kind of bug that
             * only appears after release.
             *
             * Cached, because this is three COUNT(*) queries on the busiest
             * page of the forum and the answer is furniture, not data anyone
             * acts on.
             */
            Schema\Integer::make('warrenDiscussionCount')
                ->get(fn (): int => $this->count('discussions')),

            Schema\Integer::make('warrenPostCount')
                ->get(fn (): int => $this->count('posts')),

            Schema\Integer::make('warrenMemberCount')
                ->get(fn (): int => $this->count('users')),

            /*
             * The API sort strings behind Hot and Top.
             *
             * 🚨 Sent rather than hardcoded, because the column behind Hot does
             * not have one name. It is `hotness` on a forum that has only run
             * Warren and `trending` on one that has had gamification 2.x, and
             * the sort is registered under whichever of those the database
             * actually has. A frontend that guessed would ask for a sort that
             * does not exist on half the forums this runs on, and the feed
             * would simply fail to load.
             */
            Schema\Str::make('warrenHotSort')
                ->get(fn (): string => '-'.$this->schema->rankColumn().',-createdAt'),

            Schema\Str::make('warrenTopSort')
                ->get(fn (): string => '-votes'),
        ];
    }

    protected function count(string $table): int
    {
        return (int) $this->cache->remember(
            'warren.count.'.$table,
            self::COUNT_TTL,
            fn () => $this->db->table($table)->count()
        );
    }
}
