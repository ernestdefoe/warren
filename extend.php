<?php

/*
 * This file is part of ernestdefoe/warren.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Extend;
use Flarum\Extension\ExtensionManager;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    /*
     * 🚨 Warren's own voting stands down when fof/gamification is enabled.
     *
     * Both read and write the same rows — see the migrations — so this is
     * purely about not drawing two vote controls on one post and not
     * registering the same sort twice. There is no data hand-off, nothing to
     * import and nothing stranded, which is the entire reason the schema was
     * shared rather than invented.
     *
     * The STYLING is never conditional. Warren styles gamification's controls
     * to look like the rest of the theme, so a forum that switches does not
     * end up with a Reddit layout and somebody else's buttons in the middle
     * of it.
     */
    (new Extend\Conditional())
        ->whenExtensionDisabled('fof-gamification', fn () => [
            // Warren's own vote endpoints, model bindings and sorts register
            // here.
        ]),
];
