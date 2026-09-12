import { extend, override } from 'flarum/common/extend';
import Discussion from 'flarum/common/models/Discussion';
import Icon from 'flarum/common/components/Icon';
import app from 'flarum/forum/app';

/**
 * Threaded comments, from the reply graph flarum/mentions already stores.
 *
 * Two things happen here and they are separate:
 *
 *  1. The ORDER. `postIds()` is what the stream renders and paginates from, so
 *     returning the server's thread order there threads the discussion without
 *     touching PostStream at all.
 *  2. The INDENT, and the collapse control that goes with it.
 */
export default function addThreading() {
  /*
   * 🚨 `override`, not `extend`.
   *
   * `postIds` is a relation accessor that returns a value; `extend` runs after
   * it and throws the return away. The stream would have gone on rendering in
   * post-number order while this file looked like it was working.
   */
  override(Discussion.prototype, 'postIds', function (original) {
    const thread = this.warrenThread && this.warrenThread();
    const ids = original();

    if (!thread || !Array.isArray(thread.ids)) return ids;

    /*
     * 🚨 The server's order is used only if it covers exactly the same posts.
     *
     * The two lists are built from the same query, but they are built at
     * different moments: a reply posted between them, or a post deleted, and
     * the thread order is missing an id the stream is about to ask for. A
     * short list would silently truncate the discussion — posts that exist,
     * that the reader can see, and that simply never render.
     *
     * Falling back to core's order costs the indent for one page load and
     * loses nothing.
     */
    if (thread.ids.length !== ids.length) return ids;

    return thread.ids.map(String);
  });

  // Depth, and the collapse state, keyed by post id for the current discussion.
  extend('flarum/forum/components/DiscussionPage', 'oninit', function () {
    collapsed.clear();
  });

  extend('flarum/forum/components/CommentPost', 'oninit', function () {
    const depth = depthOf(this.attrs.post);

    if (depth > 0) this.attrs.className = `${this.attrs.className || ''} Warren-reply`;
  });

  /*
   * The indent is applied to the stream ITEM rather than to the post, because
   * the item is what carries the spacing between posts. Indenting the inner
   * element leaves the gap between two replies at full width and the column of
   * thread lines breaks exactly where it matters.
   */
  extend('flarum/forum/components/PostStream', 'view', function (vnode) {
    if (!vnode) return;

    /*
     * 🚨 Walked recursively, not read off `vnode.children` once.
     *
     * A post with a time gap before it, or the one the after-first-post items
     * hang off, is wrapped in a fragment — so the items are not all siblings at
     * one level. A flat pass indents most of a discussion and silently misses
     * those, which reads as threading that "sometimes doesn't work".
     */
    walk(vnode, (item) => {
      const id = item.attrs['data-id'];
      const post = id && app.store.getById('posts', id);

      if (!post) return;

      const depth = depthOf(post);

      if (depth > 0) {
        item.attrs.className += ' Warren-threaded';
        item.attrs.style = { ...(item.attrs.style || {}), '--wr-depth': depth };
      }

      if (isHidden(post)) {
        item.attrs.className += ' Warren-hidden';
      }
    });
  });

  // The [-] control, in the strip the vote pill already lives in.
  extend('flarum/forum/components/CommentPost', 'actionItems', function (items) {
    const post = this.attrs.post;

    if (!post || !hasChildren(post)) return;

    const id = String(post.id());
    const isCollapsed = collapsed.has(id);

    items.add(
      'warrenCollapse',
      <button
        type="button"
        className="Warren-action Warren-collapse"
        aria-expanded={isCollapsed ? 'false' : 'true'}
        onclick={() => {
          if (isCollapsed) {
            collapsed.delete(id);
          } else {
            collapsed.add(id);
          }
        }}
      >
        <Icon name={isCollapsed ? 'fas fa-plus' : 'fas fa-minus'} />
        {isCollapsed
          ? app.translator.trans('ernestdefoe-warren.forum.thread.expand', { count: childCount(post) })
          : app.translator.trans('ernestdefoe-warren.forum.thread.collapse')}
      </button>,
      190
    );
  });
}

/** Post ids whose descendants are hidden. Reset when a discussion opens. */
const collapsed = new Set();

/**
 * Visit every `.PostStream-item` vnode in a tree.
 *
 * Depth is bounded rather than unbounded: this runs on every redraw of the
 * busiest component on the page, and a malformed tree should cost a wasted
 * pass, never a hang.
 */
function walk(vnode, visit, depth = 0) {
  if (!vnode || typeof vnode !== 'object' || depth > 6) return;

  if (Array.isArray(vnode)) {
    vnode.forEach((child) => walk(child, visit, depth + 1));
    return;
  }

  const className = vnode.attrs && vnode.attrs.className;

  if (typeof className === 'string' && className.includes('PostStream-item') && vnode.attrs['data-id']) {
    visit(vnode);
    return;
  }

  walk(vnode.children, visit, depth + 1);
}

function thread(post) {
  const discussion = post.discussion && post.discussion();
  const data = discussion && discussion.warrenThread && discussion.warrenThread();

  return data && Array.isArray(data.ids) ? data : null;
}

function depthOf(post) {
  const data = thread(post);

  if (!data) return 0;

  const i = data.ids.indexOf(Number(post.id()));

  return i === -1 ? 0 : data.depths[i] || 0;
}

/**
 * A post's descendants are the run immediately after it that is deeper than it
 * is — which is what depth-first order means and why the server sends the two
 * arrays together rather than a parent per post. No graph walk on the client.
 */
function descendants(post) {
  const data = thread(post);

  if (!data) return [];

  const i = data.ids.indexOf(Number(post.id()));

  if (i === -1) return [];

  const depth = data.depths[i];
  const out = [];

  for (let j = i + 1; j < data.ids.length && data.depths[j] > depth; j++) {
    out.push(String(data.ids[j]));
  }

  return out;
}

function hasChildren(post) {
  return descendants(post).length > 0;
}

function childCount(post) {
  return descendants(post).length;
}

/** Hidden when any ancestor is collapsed, not only the immediate parent. */
function isHidden(post) {
  if (!collapsed.size) return false;

  const data = thread(post);

  if (!data) return false;

  const i = data.ids.indexOf(Number(post.id()));

  if (i <= 0) return false;

  let depth = data.depths[i];

  // Walk back up the run to the root, testing each ancestor on the way.
  for (let j = i - 1; j >= 0 && depth > 0; j--) {
    if (data.depths[j] < depth) {
      depth = data.depths[j];

      if (collapsed.has(String(data.ids[j]))) return true;
    }
  }

  return false;
}
