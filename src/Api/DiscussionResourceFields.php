<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Api;

use ErnestDefoe\Warren\SharedSchema;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use s9e\TextFormatter\Utils;

/**
 * The score, the ranking, this reader's own vote, and the post preview.
 *
 * 🚨 The vote fields are READ-ONLY and registered unconditionally, including on
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
 * kinds of forum. A control that only knew how to read its own extension's
 * field would need two renderers and they would drift.
 */
class DiscussionResourceFields
{
    /** Roughly three lines at the feed's width, which is where the fade sits. */
    private const EXCERPT_LENGTH = 220;

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

            /*
             * The opening post's first image, shown full width under the title.
             *
             * One image, not a gallery. A post with four pictures in it is
             * still one post in a feed, and a row that tiles them is a
             * different layout — this one gives the first picture the width
             * and lets the thread carry the rest.
             */
            Schema\Str::make('warrenImage')
                ->visible(fn (Discussion $discussion, Context $context) => $this->canPreview($discussion, $context))
                ->get(fn (Discussion $discussion): ?string => $this->firstImage($discussion)),

            /*
             * The opening post as plain text, for posts with no picture.
             *
             * Stripped of formatting rather than rendered: an excerpt is a
             * hint, and letting post markup into a list row means one bad
             * paste can restyle the page around it.
             */
            Schema\Str::make('warrenExcerpt')
                ->visible(fn (Discussion $discussion, Context $context) => $this->canPreview($discussion, $context))
                ->get(fn (Discussion $discussion): ?string => $this->excerpt($discussion)),
        ];
    }

    /**
     * This actor's vote, read from the relation the index endpoint eager-loaded.
     *
     * 🚨 No query here, on purpose. A vote control renders on every row, so a
     * lookup per discussion is an N+1 on every page of the forum — twenty
     * extra queries to draw twenty arrows. `extend.php` eager-loads
     * `warrenVotes` already constrained to this actor, so by the time a field
     * is serialised the answer is in memory.
     *
     * The relation hangs off the discussion's own `first_post_id` rather than
     * off a loaded `firstPost`, which is why the vote costs ONE query even on
     * an endpoint carrying no posts at all.
     *
     * It is constrained by ROW (`where user_id`), never by column. Narrowing
     * the columns of a relation other code can ask for is what put a null
     * `createdAt` into Flarum's store and blanked discussion pages in Cascade.
     * A relation Warren defines under its own name cannot collide that way,
     * and it still must not be column-narrowed.
     *
     * Returns null when the relation was never loaded — an endpoint Warren did
     * not extend, or a model built in a console command. "I don't know" is the
     * honest answer there, and it renders as an un-voted control rather than
     * as a lie about what this reader did.
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

    /**
     * Only on a listing.
     *
     * 🚨 Deliberately does NOT test `relationLoaded('firstPost')`.
     *
     * That test belongs in `firstPostXml()`, and only there. `visible()` is
     * evaluated BEFORE the endpoint's eager loads have been applied to the
     * model, so a visibility gate on a loaded relation is always false and the
     * field never ships at all — the preview was silently absent from every
     * row while the extraction underneath it worked perfectly.
     *
     * The N+1 protection is not lost by moving it: `firstPostXml()` still
     * refuses to touch an unloaded relation, so if the eager load ever stops
     * matching the feed degrades to a title-only list rather than firing one
     * query per row.
     */
    protected function canPreview(Discussion $discussion, Context $context): bool
    {
        return $context->listing();
    }

    protected function firstPostXml(Discussion $discussion): ?string
    {
        if (! $discussion->relationLoaded('firstPost')) {
            return null;
        }

        $post = $discussion->getRelation('firstPost');

        if (! $post instanceof CommentPost) {
            return null;
        }

        $xml = $post->parsed_content;

        return is_string($xml) && $xml !== '' ? $xml : null;
    }

    protected function firstImage(Discussion $discussion): ?string
    {
        $xml = $this->firstPostXml($discussion);

        if ($xml === null) {
            return null;
        }

        /*
         * Attachments first, and if there are any, ONLY attachments.
         *
         * An uploaded image is deliberate media — somebody attached a photo or
         * a screenshot. An inline markdown image very often is not: a release
         * announcement is one cover image followed by a row of shields.io
         * badges, and a feed that leads with a build badge looks broken rather
         * than illustrated.
         */
        $candidates = array_merge(
            Utils::getAttributeValues($xml, 'UPL-IMAGE-PREVIEW', 'thumbnail_url'),
            Utils::getAttributeValues($xml, 'UPL-IMAGE-PREVIEW', 'url')
        );

        if ($candidates === []) {
            $candidates = array_filter(
                Utils::getAttributeValues($xml, 'IMG', 'src'),
                fn ($url) => is_string($url) && ! $this->isBadge($url)
            );
        }

        foreach ($candidates as $url) {
            if (is_string($url) && $this->isSafeUrl($url)) {
                return $url;
            }
        }

        return null;
    }

    protected function excerpt(Discussion $discussion): ?string
    {
        $xml = $this->firstPostXml($discussion);

        if ($xml === null) {
            return null;
        }

        // Attachment markup carries filenames and sizes, which read as noise
        // in a sentence of prose.
        foreach (['UPL-IMAGE-PREVIEW', 'UPL-FILE'] as $tag) {
            $xml = Utils::removeTag($xml, $tag);
        }

        $text = trim(preg_replace('/\s+/u', ' ', Utils::removeFormatting($xml)) ?? '');

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::EXCERPT_LENGTH);
        $lastSpace = mb_strrpos($cut, ' ');

        // Guard against one very long token — a URL, a CJK run with no spaces
        // — collapsing the excerpt to almost nothing.
        if ($lastSpace !== false && $lastSpace > self::EXCERPT_LENGTH * 0.6) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut).'…';
    }

    /**
     * 🚨 Only http(s) and site-relative URLs reach the browser as an `src`.
     *
     * The value comes out of post content, which is written by members. A
     * `javascript:` or `data:` URL in an img src is the oldest trick there is,
     * and a feed would render it on the busiest page of the forum.
     */
    protected function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return (bool) preg_match('#^https?://#i', $url);
    }

    /** Shields, badges and other furniture that is never the subject. */
    protected function isBadge(string $url): bool
    {
        return (bool) preg_match(
            '#(shields\.io|badgen\.net|badge\.fury\.io|travis-ci|circleci\.com|/badge/)#i',
            $url
        );
    }
}
