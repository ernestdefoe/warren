import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import ItemList from 'flarum/common/utils/ItemList';
import IndexPage from 'flarum/forum/components/IndexPage';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';

/**
 * fof/forum-widgets-core's side section, as it names its own item.
 */
const FOF_SIDE_ITEM = 'endWidgetSection';

/**
 * The right rail: the About card, and room for anything else.
 *
 * `.Page-container` is already a flex row of sidebar + content, so a third
 * child needs no layout surgery — only a width, which shell/layout.less
 * supplies.
 */
export default function addRightRail() {
  extend('flarum/forum/components/PageStructure', 'containerItems', function (items) {
    if (!app.current || !app.current.matches(IndexPage)) return;

    /*
     * fof/forum-widgets-core adds its side section to this very list, at
     * priority 1 — just below Warren's rail. Left alone that is a FOURTH
     * column in a three-column layout: the row overflows and the feed is
     * squeezed. Adopting the item into the rail gives the forum one right-hand
     * column with both sets of widgets stacked in it, which is what an
     * operator who configured side widgets actually wants.
     */
    let sideWidgets = null;

    if (items.has(FOF_SIDE_ITEM)) {
      sideWidgets = items.get(FOF_SIDE_ITEM);
      items.remove(FOF_SIDE_ITEM);
    }

    const widgets = widgetItems().toArray();

    // An empty 316px column pushes the feed off centre for nothing, and the
    // feed centres perfectly well without it.
    if (!widgets.length && !sideWidgets) return;

    // Priority below 'content' (10) so it lands after the feed column.
    items.add(
      'warrenRail',
      <aside className="Warren-rail">
        {widgets}
        {sideWidgets}
      </aside>,
      5
    );
  });
}

export function widgetItems() {
  const items = new ItemList();

  /*
   * 🚨 CALLED, not rendered as <AboutCard />.
   *
   * Mithril treats a bare function tag as a CLOSURE component: it calls the
   * function once and expects an object with a `view` method back. A function
   * that returns a vnode instead hands Mithril something with no `view`, and
   * the next redraw dies inside render.js on `undefined.apply` — which takes
   * the whole page down, not just the rail. The discussion list spins forever
   * and nothing in the error names this file.
   *
   * Either shape is fine; a plain function has to be invoked.
   */
  // Off means off: the rail renders nothing rather than an empty panel, and
  // the column below is dropped entirely when it has no widgets.
  if (app.forum.attribute('warrenShowAbout') !== false) {
    items.add('about', aboutCard(), 100);
  }

  return items;
}

function aboutCard() {
  const forum = app.forum;
  const description = forum.attribute('description');

  return (
    <div className="Warren-card Warren-card--about">
      <div className="Warren-card-head Warren-card-head--accent">
        {app.translator.trans('ernestdefoe-warren.forum.rail.about')}
      </div>

      <div className="Warren-card-body">
        {description ? <p className="Warren-about-text">{description}</p> : null}

        {/*
          * Warren's own counts, not another theme's.
          *
          * Core publishes none on the forum resource, and the ones present on
          * any given forum belong to whichever other theme is installed —
          * `respawnPostCount`, `mosaicUserCount`. Reading one of those works
          * on a forum that happens to have it and shows zeros everywhere else.
          */}
        <div className="Warren-card-stat">
          <span>{app.translator.trans('ernestdefoe-warren.forum.rail.members')}</span>
          <span>{formatCount(forum.attribute('warrenMemberCount'))}</span>
        </div>

        <div className="Warren-card-stat">
          <span>{app.translator.trans('ernestdefoe-warren.forum.rail.discussions')}</span>
          <span>{formatCount(forum.attribute('warrenDiscussionCount'))}</span>
        </div>

        <div className="Warren-card-stat">
          <span>{app.translator.trans('ernestdefoe-warren.forum.rail.comments')}</span>
          <span>{formatCount(forum.attribute('warrenPostCount'))}</span>
        </div>

        {/*
          * 🚨 Only when the actor can actually start one.
          *
          * A primary-coloured button that answers a click with a permission
          * error is worse than no button — it is the forum telling a reader
          * they are welcome and then refusing them.
          */}
        {app.forum.attribute('canStartDiscussion') ? (
          <Button
            className="Button Button--primary Warren-about-cta"
            onclick={() => app.composer.load(() => import('flarum/forum/components/DiscussionComposer'), { user: app.session.user })}
          >
            <Icon name="fas fa-plus" />
            {app.translator.trans('ernestdefoe-warren.forum.rail.create')}
          </Button>
        ) : null}
      </div>
    </div>
  );
}

function formatCount(value) {
  const n = Number(value || 0);

  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}m`;
  if (n >= 10000) return `${(n / 1000).toFixed(0)}k`;

  return n.toLocaleString();
}
