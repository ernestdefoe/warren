import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import listItems from 'flarum/common/helpers/listItems';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/utils/humanTime';

import VoteGutter from './components/VoteGutter';

/**
 * Turn the discussion list row into a link row with a vote gutter.
 *
 * The row is a two-column grid. The gutter spans every row of column one;
 * everything else stacks down column two in the order the ItemList puts it:
 *
 *   byline    community · posted by · when
 *   main      core's title and info line, untouched
 *   actions   comments · share
 *
 * 🚨 The byline and the action bar are SIBLINGS of core's main view, not
 * children of it. `mainView()` is a `<Link>`, so a button nested inside it
 * would be a button inside an anchor — invalid HTML that browsers un-nest on
 * their own, moving the control somewhere nobody styled. There is also no
 * `mainItems` ItemList to add to; the first version of this file assumed there
 * was, and rendered nothing at all.
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

    // 110 puts the gutter above core's author (100), so it is the row's first
    // child and lands in grid column one with no ordering rules at all.
    items.add('warrenGutter', <VoteGutter discussion={discussion} />, 110);
    items.add('warrenByline', bylineView(discussion), 95);
    items.add('warrenActions', actionsView(discussion), 60);

    /*
     * 🚨 Core's author item is removed, and its BADGES are re-rendered below.
     *
     * Removing it on its own is the trap: core renders the discussion badges
     * inside the same item, so sticky, locked and every badge another
     * extension contributes would quietly stop appearing on the list, with
     * nothing to connect the loss to this line. The byline renders
     * `discussion.badges()` itself, which is the same ItemList every one of
     * those extensions adds to.
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

  return (
    <div className="Warren-byline">
      {tag ? (
        <Link className="Warren-community" href={app.route.tag(tag)}>
          <span
            className="Warren-community-dot"
            style={tag.color() ? { background: tag.color() } : null}
          />
          {tag.name()}
        </Link>
      ) : null}

      <span className="Warren-byline-meta">
        {app.translator.trans('ernestdefoe-warren.forum.row.posted_by', {
          user: user ? <Link href={app.route.user(user)}>{username(user)}</Link> : username(user),
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

function actionsView(discussion) {
  const count = discussion.commentCount() || 0;

  return (
    <div className="Warren-actions">
      <Link className="Warren-action" href={app.route.discussion(discussion)}>
        <Icon name="far fa-comment-alt" />
        {app.translator.trans('ernestdefoe-warren.forum.row.comments', { count })}
      </Link>

      <button
        type="button"
        className="Warren-action"
        onclick={(e) => {
          e.preventDefault();
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
 * dead control: it is built, worded, styled and does its job invisibly, so
 * everyone assumes it is broken. The alert is the feedback.
 */
function share(discussion) {
  const url = app.forum.attribute('baseUrl') + app.route.discussion(discussion);

  const done = () => app.alerts.show(
    { type: 'success' },
    app.translator.trans('ernestdefoe-warren.forum.row.share_copied')
  );

  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(done, () => window.prompt('', url));
  } else {
    window.prompt('', url);
  }
}
