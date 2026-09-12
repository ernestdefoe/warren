import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import listItems from 'flarum/common/helpers/listItems';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/utils/humanTime';

import VoteGutter from './components/VoteGutter';

/**
 * Turn the discussion list row into a post.
 *
 * This ADDS to DiscussionListItem's ItemLists rather than replacing the
 * component:
 *
 *   byline    community mark · community · posted by · when
 *   main      core's title and info line, untouched
 *   actions   the vote pill, the comment count, share
 *
 * 🚨 The byline and the action strip are SIBLINGS of core's main view, not
 * children of it. `mainView()` is a `<Link>`, so a button nested inside it
 * would be a button inside an anchor — invalid HTML that browsers un-nest on
 * their own, moving the control somewhere nobody styled. There is also no
 * `mainItems` ItemList to add to; an earlier version of this file assumed
 * there was and rendered nothing at all.
 */
export default function decorateRow() {
  /*
   * 🚨 A module PATH, not an imported prototype.
   *
   * Core's `extend()` resolves a string through flarum.reg.onLoad, so it keeps
   * working when a component lives in an async chunk. Importing one that has
   * not been registered yet throws at boot, which takes down every page rather
   * than one component.
   */
  extend('flarum/forum/components/DiscussionListItem', 'contentItems', function (items) {
    const discussion = this.attrs.discussion;

    items.add('warrenByline', bylineView(discussion), 110);

    const preview = previewView(discussion);

    if (preview) items.add('warrenPreview', preview, 70);

    items.add('warrenActions', actionsView(discussion), 60);

    /*
     * 🚨 Core's author item is removed, and its BADGES are re-rendered below.
     *
     * Removing it on its own is the trap: core renders the discussion badges
     * inside the same item, so sticky, locked and every badge another
     * extension contributes would quietly stop appearing on the list, with
     * nothing to connect the loss to this line. The byline renders
     * `discussion.badges()` itself, which is the same ItemList all of those
     * extensions add to.
     */
    items.remove('author');
  });

  /*
   * 🚨 Declare what can change to the row's own SubtreeRetainer.
   *
   * DiscussionListItem freezes its subtree behind a retainer keyed on
   * `discussion.freshness`. A vote changes the score without touching
   * freshness, so without this the model updates and the DOM never follows —
   * which looks exactly like an arrow that does nothing.
   */
  extend('flarum/forum/components/DiscussionListItem', 'oninit', function () {
    this.subtree.check(
      () => this.attrs.discussion.warrenScore(),
      () => this.attrs.discussion.warrenUserVote()
    );
  });
}

function bylineView(discussion) {
  const user = discussion.user();
  const tags = discussion.tags && discussion.tags();
  const tag = tags && tags.length ? tags[0] : null;
  const badges = discussion.badges().toArray();

  const mark = (
    <span
      className="Warren-community-dot"
      style={tag && tag.color() ? { background: tag.color() } : null}
    />
  );

  return (
    <div className="Warren-byline">
      {tag ? (
        <Link className="Warren-community" href={app.route.tag(tag)}>
          {mark}
          {tag.name()}
        </Link>
      ) : (
        <span className="Warren-community">{mark}</span>
      )}

      <span className="Warren-byline-meta">
        {/*
          * 🚨 The placeholder is `author`, NOT `user`.
          *
          * Flarum's translator gives a parameter literally named `user`
          * special handling: it runs the value through the username helper,
          * which calls `displayName()` on it. Passing a vnode — a link around
          * the name, which is the whole point here — throws `t.displayName is
          * not a function` from inside the translator.
          *
          * The throw happens while this ItemList callback is still running, so
          * everything added AFTER the failing line is silently missing from
          * the row, and nothing in the error names either piece.
          */}
        {app.translator.trans('ernestdefoe-warren.forum.row.posted_by', {
          author: user ? <Link href={app.route.user(user)}>{username(user)}</Link> : username(user),
        })}
        <span className="Warren-byline-sep">·</span>
        {humanTime(discussion.createdAt())}
      </span>

      {badges.length ? (
        <ul className="DiscussionListItem-badges badges badges--packed">{listItems(badges)}</ul>
      ) : null}
    </div>
  );
}

/**
 * The picture, or the first couple of lines, or nothing.
 *
 * A post with an image leads with it; a post without one shows the opening
 * words. Never both: a row carrying a picture AND three lines of prose is
 * taller than two rows that each carry one, and a feed is a list before it is
 * a gallery.
 */
function previewView(discussion) {
  const image = discussion.warrenImage && discussion.warrenImage();

  if (image) {
    return (
      <Link className="Warren-media" href={app.route.discussion(discussion)}>
        {/*
          * 🚨 loading="lazy" and an empty alt.
          *
          * Lazy because a feed of twenty posts is twenty full-size images the
          * reader has not scrolled to yet. Empty alt because the link around
          * it is already labelled by the title directly above - a screen
          * reader announcing the filename here would read the post twice.
          */}
        <img src={image} alt="" loading="lazy" />
      </Link>
    );
  }

  const excerpt = discussion.warrenExcerpt && discussion.warrenExcerpt();

  if (excerpt) {
    return (
      <Link className="Warren-excerpt" href={app.route.discussion(discussion)}>
        {excerpt}
      </Link>
    );
  }

  return null;
}

function actionsView(discussion) {
  const count = discussion.commentCount() || 0;

  return (
    <div className="Warren-actions">
      <VoteGutter discussion={discussion} />

      <Link className="Warren-action" href={app.route.discussion(discussion)}>
        <Icon name="far fa-comment-alt" />
        {app.translator.trans('ernestdefoe-warren.forum.row.comments', { count })}
      </Link>

      <button
        type="button"
        className="Warren-action"
        onclick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          share(discussion);
        }}
      >
        <Icon name="fas fa-share" />
        {app.translator.trans('ernestdefoe-warren.forum.row.share')}
      </button>
    </div>
  );
}

/**
 * 🚨 Copies the link, and SAYS SO.
 *
 * A Share button that opens nothing and shows nothing is the commonest kind of
 * dead control: built, worded, styled, and doing its job invisibly, so
 * everyone assumes it is broken. The alert is the feedback.
 */
function share(discussion) {
  const url = app.forum.attribute('baseUrl') + app.route.discussion(discussion);

  const done = () =>
    app.alerts.show(
      { type: 'success' },
      app.translator.trans('ernestdefoe-warren.forum.row.share_copied')
    );

  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(done, () => window.prompt('', url));
  } else {
    window.prompt('', url);
  }
}
