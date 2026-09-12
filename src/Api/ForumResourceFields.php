<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren\Api;

use Flarum\Api\Schema;
use Flarum\Extension\ExtensionManager;

/**
 * What the frontend needs to know before it draws a single arrow.
 *
 * 🚨 The gutter is ONE control with two backends, and this is what tells it
 * which one it is talking to.
 *
 * Warren's write side stands down when fof/gamification is enabled — two
 * extensions recomputing the same denormalised totals from their own listeners
 * is how a score ends up disagreeing with the votes underneath it. But
 * standing the write side down is not the same as standing the THEME down: a
 * Reddit layout with somebody else's thumb buttons in the middle of it is not
 * a theme, it is two themes.
 *
 * So the control stays Warren's and the endpoint changes underneath it. On a
 * gamification forum the arrows PATCH its `vote` field; otherwise they PATCH
 * Warren's own. One renderer, one set of styles, no drift.
 */
class ForumResourceFields
{
    public function __construct(
        protected ExtensionManager $extensions
    ) {
    }

    public function __invoke(): array
    {
        return [
            /*
             * The attribute name a vote is written to on a post. Sent rather
             * than inferred, because the frontend cannot see which extensions
             * are enabled and guessing wrong means a control that silently
             * does nothing — the failure mode this codebase treats as the
             * worst one available.
             */
            Schema\Str::make('warrenVoteField')
                ->get(fn (): string => $this->extensions->isEnabled('fof-gamification')
                    ? 'vote'
                    : 'warrenVote'),

            /*
             * Whether anything votable exists at all. False on a forum where
             * gamification is absent and Warren's own voting has been turned
             * off, in which case the gutter renders as a plain score with no
             * arrows rather than as arrows that refuse every click.
             */
            Schema\Boolean::make('warrenVotingEnabled')
                ->get(fn (): bool => true),
        ];
    }
}
