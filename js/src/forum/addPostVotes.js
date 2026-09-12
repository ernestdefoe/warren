import { extend } from 'flarum/common/extend';

import PostVotes from './components/PostVotes';

/**
 * The vote pill on every comment in a thread.
 *
 * 🚨 `actionItems`, not `footerItems`. The action list is the strip core
 * already renders at the foot of a post with Reply and Like in it, so the
 * arrows land beside the controls they belong with and inherit the same
 * spacing. The footer is a different, mostly empty region, and a control put
 * there looks orphaned on a theme that styles neither.
 *
 * 200 puts it first, ahead of Reply — the same order as the list row, where
 * the vote control leads the strip.
 */
export default function addPostVotes() {
  extend('flarum/forum/components/CommentPost', 'actionItems', function (items) {
    const post = this.attrs.post;

    // An event post — a rename, a tag change, a merge — is not an opinion and
    // has nothing to vote on. The server refuses it too; this is so the
    // control is never drawn in the first place.
    if (!post || post.contentType() !== 'comment') return;

    if (!post.warrenCanVote || post.warrenCanVote() === undefined) return;

    items.add('warrenVotes', <PostVotes post={post} />, 200);
  });

  /*
   * 🚨 Declare the vote to the post's own SubtreeRetainer.
   *
   * CommentPost freezes its subtree on a handful of keys, none of which move
   * when a vote lands. Without this the model updates and the DOM never
   * follows — the arrow looks dead, which is precisely the failure this
   * codebase treats as the worst one available.
   */
  extend('flarum/forum/components/CommentPost', 'oninit', function () {
    if (!this.subtree) return;

    this.subtree.check(
      () => this.attrs.post.warrenUpvotes && this.attrs.post.warrenUpvotes(),
      () => this.attrs.post.warrenDownvotes && this.attrs.post.warrenDownvotes(),
      () => this.attrs.post.warrenUserVote && this.attrs.post.warrenUserVote()
    );
  });
}
