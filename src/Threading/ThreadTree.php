<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Threading;

use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * A discussion's posts in reply order, with a depth for each.
 *
 * 🚨 There is no new table and no migration. The reply graph already exists:
 * flarum/mentions has written `post_mentions_post` every time somebody quoted
 * or replied to a post since 2015. Threading a forum that has been running for
 * years therefore works on its whole history the moment the theme is enabled,
 * which is not true of any design that starts recording a parent from now on.
 *
 * 🚨 Computed on the server because it CANNOT be computed on the client. The
 * order depends on the parent of every post in the discussion, and the browser
 * only ever holds the page it is looking at — it cannot know that post 400
 * belongs under post 3 until it has fetched both.
 */
class ThreadTree
{
    /**
     * How deep the indent is allowed to go.
     *
     * Past this the reply is still a reply and still sits under its parent in
     * the order; it just stops moving right. Without a cap a long back-and-forth
     * walks off the side of the column and the last few posts are a sliver.
     */
    public const MAX_DEPTH = 8;

    /** Per-request memo: a discussion page asks for this more than once. */
    protected array $cache = [];

    protected ?bool $graphExists = null;

    public function __construct(
        protected ConnectionInterface $db,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * 🚨 Clamped, not taken as given.
     *
     * The value comes from an admin field, and a 0 there would flatten every
     * discussion while the threading code went on running — a feature that
     * looks broken rather than switched off. A huge one walks the column off
     * the side of the page. Neither is a setting anyone means to choose.
     */
    protected function maxDepth(): int
    {
        $depth = (int) $this->settings->get('ernestdefoe-warren.thread_depth', self::MAX_DEPTH);

        return max(1, min($depth, 20));
    }

    /**
     * @return array{ids: list<int>, depths: list<int>}
     */
    public function for(Discussion $discussion, User $actor): array
    {
        $key = $discussion->id.':'.($actor->id ?? 0);

        return $this->cache[$key] ??= $this->build($discussion, $actor);
    }

    /**
     * @return array{ids: list<int>, depths: list<int>}
     */
    protected function build(Discussion $discussion, User $actor): array
    {
        /*
         * The same query core uses for the post linkage, in the same order and
         * with the same visibility rule, so the two lists always hold exactly
         * the same posts. A thread order that disagreed with the linkage would
         * render a post the stream cannot fetch, or silently drop one.
         */
        $rows = $discussion->posts()
            ->whereVisibleToInDiscussion($actor, $discussion)
            ->orderBy('posts.number')
            ->toBase()
            ->get(['posts.id', 'posts.number']);

        $order = [];
        $numbers = [];

        foreach ($rows as $row) {
            $order[] = (int) $row->id;
            $numbers[(int) $row->id] = (int) $row->number;
        }

        if (count($order) < 2 || ! $this->hasGraph()) {
            // Nothing to thread, or nothing to thread it from. A flat list is
            // the honest answer, not an error.
            return ['ids' => $order, 'depths' => array_fill(0, count($order), 0)];
        }

        $parents = $this->parents($order, $numbers);

        $children = [];
        $roots = [];

        foreach ($order as $id) {
            $parent = $parents[$id] ?? null;

            if ($parent === null) {
                $roots[] = $id;
            } else {
                $children[$parent][] = $id;
            }
        }

        $ids = [];
        $depths = [];
        $max = $this->maxDepth();

        /*
         * Depth-first, iteratively.
         *
         * 🚨 Not recursive. A discussion is user-supplied data and a chain of
         * ten thousand replies is a legal one; recursion there is a stack
         * overflow, which in PHP is a segfault with no error message and no log
         * line. The stack is explicit so the worst case is slow, not fatal.
         */
        $stack = array_reverse(array_map(fn ($id) => [$id, 0], $roots));

        while ($stack) {
            [$id, $depth] = array_pop($stack);

            $ids[] = $id;
            $depths[] = min($depth, $max);

            foreach (array_reverse($children[$id] ?? []) as $child) {
                $stack[] = [$child, $depth + 1];
            }
        }

        return ['ids' => $ids, 'depths' => $depths];
    }

    /**
     * One parent per post, or none.
     *
     * @param  list<int>      $order
     * @param  array<int,int> $numbers  post id => post number
     * @return array<int,int> post id => parent post id
     */
    protected function parents(array $order, array $numbers): array
    {
        $rows = $this->db->table('post_mentions_post')
            ->whereIn('post_id', $order)
            ->whereIn('mentions_post_id', $order)
            ->get(['post_id', 'mentions_post_id']);

        $parents = [];

        foreach ($rows as $row) {
            $child = (int) $row->post_id;
            $parent = (int) $row->mentions_post_id;

            /*
             * 🚨 A parent must come BEFORE its child, and this is the only
             * cycle protection the tree has — deliberately, because it is
             * total. Post numbers are strictly increasing within a discussion,
             * so an edge that only ever points backwards cannot close a loop,
             * and the walk below needs no visited-set and cannot hang.
             *
             * Mentions is a many-to-many and it records edges in both
             * directions over time: edit an old post to quote a newer one and
             * there is a forward edge in the table. Without this test that edge
             * is a cycle, and a depth-first walk over it never terminates —
             * a hung request on a page anyone can reach.
             */
            if ($parent === $child || ($numbers[$parent] ?? PHP_INT_MAX) >= ($numbers[$child] ?? 0)) {
                continue;
            }

            /*
             * A post may quote several others. Reddit has exactly one parent,
             * so the LATEST thing a post replies to wins: quoting an old post
             * for context and then answering a recent one should nest under
             * the one being answered.
             */
            if (! isset($parents[$child]) || $numbers[$parent] > $numbers[$parents[$child]]) {
                $parents[$child] = $parent;
            }
        }

        return $parents;
    }

    /**
     * 🚨 flarum/mentions may not be installed, and Warren does not require it.
     *
     * Its table is the only place the reply graph lives, so without it every
     * discussion renders flat — which is exactly what a forum that has never
     * recorded who replied to whom should look like. Asking once per process
     * rather than per discussion.
     */
    protected function hasGraph(): bool
    {
        return $this->graphExists ??= $this->db->getSchemaBuilder()->hasTable('post_mentions_post');
    }
}
