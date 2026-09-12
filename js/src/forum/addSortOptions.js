import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';

/**
 * Put Hot and Top in the sort control.
 *
 * 🚨 The client's sort map is a SEPARATE, hardcoded list from the server's.
 * Registering a sort in PHP makes the API accept it; it does not make the
 * dropdown offer it. A sort nobody can reach from the UI is the same as no
 * sort at all.
 */
export default function addSortOptions() {
  extend('flarum/forum/states/DiscussionListState', 'sortMap', function (map) {
    /*
     * 🚨 The API sort strings come from the server.
     *
     * The column behind Hot does not have one name — `hotness` on a forum that
     * has only run Warren, `trending` once gamification 2.x has migrated it —
     * and the sort is registered under whichever of those the database has. A
     * hardcoded guess asks for a sort that does not exist on half the forums
     * this runs on, and the feed fails to load rather than falling back.
     */
    const hot = app.forum.attribute('warrenHotSort');
    const top = app.forum.attribute('warrenTopSort');

    if (!hot || !top) return;

    /*
     * 🚨 The map is REBUILT, not appended to.
     *
     * Key order is the order of the dropdown, and the first key is what the
     * control falls back to displaying. Appending would bury Hot under six
     * core options in the theme whose whole premise is that Hot is the
     * default view.
     *
     * `relevance` is preserved in place when core added it — it only exists
     * during a search, and moving it would change what a search page opens on.
     */
    const rebuilt = {};

    if (map.relevance !== undefined) rebuilt.relevance = map.relevance;

    rebuilt.hot = { sort: hot, label: app.translator.trans('ernestdefoe-warren.forum.sort.hot') };
    rebuilt.latest = map.latest;
    rebuilt.newest = map.newest;
    rebuilt.votes = { sort: top, label: app.translator.trans('ernestdefoe-warren.forum.sort.top') };

    // Everything core offered that is not already placed, in core's own order,
    // so a forum that relied on `az` or `oldest` keeps them.
    Object.keys(map).forEach((key) => {
      if (rebuilt[key] === undefined) rebuilt[key] = map[key];
    });

    Object.keys(map).forEach((key) => delete map[key]);
    Object.assign(map, rebuilt);
  });
}
