import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/utils/humanTime';

import VoteGutter from './components/VoteGutter';

/**
 * Turn the discussion list row into a link row with a vote gutter.
 *
 * This ADDS to DiscussionListItem's ItemLists rather than replacing the
 * component. Core's own items — the title, the info line where flarum/tags
 * puts its labels and core puts the terminal post — all stay where they are,
 * which is what lets tags, best-answer, sticky, locked and every other list
 * decorator keep working untouched.
 *
 * The row becomes a two-column grid: the gutter spans every row of column one,
 * and everything core renders falls into column two. Nothing is re-parented,
 * so nothing that walks the DOM for core's classes breaks.
 */
export default function decorateRow() {
  /*
   * 🚨 A module PATH, not an imported prototype.
   *
   * Core's `extend()` resolves a string through flarum.reg.onLoad, so it keeps
   * working when a component lives in an async chunk. Importing one that has
   * not been registered yet throws at boot, which takes down every page rather
   * than one component — the trap that broke Cascade's settings picker on
   * every page of the forum.
   */
  extend('flarum/forum/components/DiscussionListItem', 'contentItems', function (items) {
    const discussion = this.attrs.discussion;

    // 110 puts it above core's author (100), so the gutter is the row's first
    // child and lands in grid column one without any ordering rules.
    items.add('warrenGutter', <VoteGutter discussion={discussion} />, 110);

    /*
     * 🚨 Core's author item is KEPT, not removed.
     *
     * Warren draws its own byline with a small avatar in it, so the obvious
     * move is `items.remove('author')` — and that also removes the discussion
     * BADGES, which core renders inside the same item. Sticky, locked and
     * every badge another extension contributes would quietly stop appearing
     * on the list, with nothing to connect the loss to this line.
     *
     * The avatar inside it is hidden in CSS instead, where hiding a picture is
     * all that happens.
     */
  });

  extend('flarum/forum/components/DiscussionListItem', 'mainItems', function (items) {
    const discussion = this.attrs.discussion;

    items.add('warrenByline', bylineView(discussion), 110);
    items.add('warrenActions', actionsView(discussion), -10);
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

  return (
    <div className="Warren-byline">
      {tag ? (
        <Link className="Warren-byline-tag" href={app.route.tag(tag)}>
          {tag.name()}
        </Link>
      ) : null}
      {tag ? <span className="Warren-byline-sep">·</span> : null}
      {Avatar.component({ user, title: '' })}
      {user ? <Link href={app.route.user(user)}>{username(user)}</Link> : username(user)}
      <span className="Warren-byline-sep">·</span>
      {humanTime(discussion.createdAt())}
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
    </div>
  );
}
