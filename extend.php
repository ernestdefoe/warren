<?php

/*
 * This file is part of ernestdefoe/warren.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ErnestDefoe\Warren\Access;
use ErnestDefoe\Warren\Api;
use ErnestDefoe\Warren\SharedSchema;
use ErnestDefoe\Warren\Vote;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Sort\SortColumn;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Frontend\Document;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        /*
         * Stamped on <html> by the server, not set from JS on first draw.
         *
         * Every colour and every width in the stylesheet hangs off this
         * attribute. Applying it after the bundle boots means the first paint
         * is unstyled and the page visibly reflows — the flash a theme is
         * judged by, on the one load where a reader has nothing else to look
         * at.
         */
        ->content(function (Document $document) {
            $density = resolve(SettingsRepositoryInterface::class)
                ->get('warren.density', 'card');

            $document->extraAttributes['data-warren'] = in_array($density, ['card', 'compact'], true)
                ? $density
                : 'card';
        }),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    /*
     * The actor's own vote on a discussion, in one query for a whole page.
     *
     * 🚨 Hung off `first_post_id` rather than off a loaded `firstPost`. Going
     * through the post relation would be the obvious spelling and it costs an
     * extra query that drags twenty post bodies across the wire to find out
     * which way twenty arrows point. The discussion already carries the id.
     */
    (new Extend\Model(Discussion::class))
        ->hasMany('warrenVotes', Vote::class, 'post_id', 'first_post_id'),

    (new Extend\Model(Post::class))
        ->hasMany('warrenVotes', Vote::class, 'post_id'),

    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(Api\ForumResourceFields::class),

    (new Extend\ApiResource(Resource\DiscussionResource::class))
        ->fields(Api\DiscussionResourceFields::class)
        ->endpoint(['index', 'show'], function (Endpoint\Index|Endpoint\Show $endpoint) {
            /*
             * 🚨 Constrained by ROW, never by column.
             *
             * `where user_id` narrows which rows come back, which is this
             * extension's business. Narrowing the COLUMNS of a relation is
             * what blanked discussion pages in Cascade — another extension
             * included the relation, serialised the half-loaded models, and
             * the frontend store kept posts with a null `createdAt` that
             * core's PostStream dereferences without a guard.
             *
             * `warrenVotes` is Warren's own relation under its own name, so
             * nothing else can be asking for it — and it still is not
             * column-narrowed, because that argument holds until the day
             * somebody else has a reason to.
             */
            $rank = resolve(SharedSchema::class)->rankColumn();

            return $endpoint
                /*
                 * 🚨 Hot is the default, and it needs a TIE-BREAK to be an
                 * ordering at all.
                 *
                 * The ranking is reddit's, and reddit's returns exactly 0 for
                 * every discussion with a score of 0 — the sign term zeroes
                 * the time term. On a forum that has not been voted on yet
                 * that is EVERY discussion, so sorting on the column alone
                 * hands back rows in whatever order the database felt like.
                 * The front page of a new install would look shuffled.
                 *
                 * `-createdAt` after it costs nothing once scores exist and
                 * makes the empty case read as newest-first, which is what a
                 * forum with no votes should look like.
                 *
                 * Fixing this in the arithmetic instead — seeding zero-score
                 * rows with their age — was the other option and it is the
                 * wrong one: the column is shared, and an extension that
                 * writes a different number than its neighbour for the same
                 * row is how a front page reorders itself depending on who
                 * touched it last.
                 */
                ->defaultSort('-'.$rank.',-createdAt')
                /*
                 * 🚨 The WHOLE post, never a column subset.
                 *
                 * The preview needs `parsed_content`, and the obvious saving
                 * is to select only that. It is the wrong saving: this eager
                 * load is SHARED, so the moment any other extension includes
                 * `firstPost` on the discussion index, those posts are
                 * serialised from the models Warren narrowed and reach the
                 * browser with a null `createdAt`. Flarum's store keeps the
                 * half-loaded Post, and core's PostStream dereferences that
                 * date without a guard — every discussion page then renders
                 * blank. That is a real bug, reported against Cascade, and
                 * impossible to reproduce without the other extension
                 * present, which is what made it look like somebody else's.
                 */
                ->eagerLoad('firstPost')
                ->eagerLoadWhere('warrenVotes', function ($query, Context $context) {
                // A guest has no votes to find. Coercing to 0 rather than
                // skipping keeps the relation MARKED loaded, which is what
                // tells the field the difference between "no vote" and "never
                // asked" — a null id would match nothing but still leave the
                // field guessing.
                $query->where('user_id', $context->getActor()->id ?? 0);
            });
        }),

    /*
     * 🚨 Warren's own voting stands down when fof/gamification is enabled.
     *
     * Both read and write the same rows — see the migrations — so this is
     * purely about not drawing two vote controls on one post, not registering
     * the same sort twice, and not having two sets of listeners recompute the
     * same denormalised totals. There is no data hand-off, nothing to import
     * and nothing stranded, which is the entire reason the schema was shared
     * rather than invented.
     *
     * The READ fields above are unconditional for the same reason: they report
     * whatever is in the shared columns, whoever wrote it.
     *
     * The STYLING is never conditional either. Warren styles gamification's
     * controls to look like the rest of the theme, so a forum that switches
     * does not end up with a Reddit layout and somebody else's buttons in the
     * middle of it.
     */
    (new Extend\Conditional())
        ->whenExtensionDisabled('fof-gamification', fn () => [
            (new Extend\Policy())
                ->modelPolicy(Post::class, Access\PostPolicy::class),

            (new Extend\ApiResource(Resource\PostResource::class))
                ->fields(Api\PostResourceFields::class),

            /*
             * Hot and Top.
             *
             * The column behind Hot is asked for rather than named:
             * gamification 2.x renames `hotness` to `trending`, and a forum
             * that installed it and later turned it off keeps the new name
             * while falling back to Warren's sorts.
             *
             * The ALIASES are what the frontend asks for, and they are stable
             * across both. Core already spends `top` on comment count, so
             * Warren's Top is `-warrenScore` — an alias nobody else claims
             * beats a nicer word that collides.
             */
            (new Extend\ApiResource(Resource\DiscussionResource::class))
                ->sorts(fn () => [
                    SortColumn::make(resolve(SharedSchema::class)->rankColumn())
                        ->descendingAlias('hot'),
                    SortColumn::make('votes')
                        ->descendingAlias('warrenScore'),
                ]),

            (new Extend\Settings())
                ->default('warren.allow_self_votes', true)
                ->serializeToForum('warrenCanVoteHere', 'warren.allow_self_votes', 'boolval'),
        ]),
];
