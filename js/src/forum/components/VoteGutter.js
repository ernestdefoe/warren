import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';

/**
 * The vote pill: two arrows around a score, sitting in the action strip at the
 * foot of a post.
 *
 * 🚨 Horizontal, and in the action strip. A column of arrows down the left of
 * a card is the older layout; this one moved them years ago, and it is the
 * single change that stops the page reading right no matter how well the
 * colours are matched.
 *
 * 🚨 One control, two backends.
 *
 * Warren's own vote endpoint stands down when fof/gamification is enabled —
 * two extensions recomputing the same denormalised totals is how a score ends
 * up disagreeing with the votes under it. But standing the write side down is
 * not standing the THEME down: a Reddit layout with somebody else's thumb
 * buttons in the middle of it is two themes, not one.
 *
 * So the control is always this one and the field it writes changes
 * underneath. The server says which in `warrenVoteField`, because the frontend
 * cannot see which extensions are enabled and a wrong guess is a control that
 * silently does nothing.
 */
export default class VoteGutter extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    /*
     * The optimistic copy the arrows render from.
     *
     * A vote is a round trip, and an arrow that waits for one feels broken on
     * any connection worse than a desk. These hold the answer we expect; the
     * response confirms them and a failure puts them back.
     */
    this.score = null;
    this.vote = null;
    this.saving = false;
  }

  view() {
    const discussion = this.attrs.discussion;

    const score = this.score === null ? discussion.warrenScore() || 0 : this.score;
    const vote = this.currentVote();

    return (
      <div className={classList('Warren-votes', vote && `Warren-votes--${vote}`)}>
        {this.arrow('up', vote === 'up')}
        <span className="Warren-score">{this.format(score)}</span>
        {this.arrow('down', vote === 'down')}
      </div>
    );
  }

  arrow(direction, active) {
    const label = app.translator.trans(`ernestdefoe-warren.forum.vote.${direction}`);

    return (
      <button
        type="button"
        className={classList(
          'Warren-arrow',
          `Warren-arrow--${direction}`,
          active && 'Warren-arrow--active'
        )}
        // A guest sees the arrows and is sent to log in, which is what every
        // site with this control does. Hiding them would hide the score's
        // explanation along with them.
        disabled={this.saving}
        aria-pressed={active ? 'true' : 'false'}
        aria-label={label}
        title={label}
        onclick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          this.cast(direction, active);
        }}
      >
        <Icon name={direction === 'up' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'} />
      </button>
    );
  }

  /**
   * Abbreviated, and not only for width: a five-digit score makes the arrows
   * on either side of it shuffle sideways every time the number grows.
   */
  format(score) {
    if (score >= 10000) return `${(score / 1000).toFixed(0)}k`;
    if (score >= 1000) return `${(score / 1000).toFixed(1)}k`;

    return String(score);
  }

  currentVote() {
    return this.vote === null ? this.attrs.discussion.warrenUserVote() : this.vote;
  }

  cast(direction, active) {
    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
      return;
    }

    const discussion = this.attrs.discussion;

    /*
     * 🚨 The vote is written to the POST, and on a list row the post is not
     * loaded — only its id is on the discussion. Without an id there is
     * nothing to PATCH, so the arrows stay put rather than firing a request
     * that would 404.
     */
    const firstPost = discussion.firstPost();
    const postId = firstPost
      ? firstPost.id()
      : discussion.data.relationships?.firstPost?.data?.id;

    if (!postId) return;

    const before = { score: this.score, vote: this.vote };
    const was = this.currentVote();

    // Clicking the arrow you already chose clears the vote, which is what the
    // server does too — the two have to agree or the optimistic number is
    // wrong for the length of a round trip.
    const next = active ? null : direction;
    const delta =
      (next === 'up' ? 1 : next === 'down' ? -1 : 0) -
      (was === 'up' ? 1 : was === 'down' ? -1 : 0);

    this.vote = next;
    this.score = (this.score === null ? discussion.warrenScore() || 0 : this.score) + delta;
    this.saving = true;

    const field = app.forum.attribute('warrenVoteField') || 'warrenVote';

    app
      .request({
        method: 'PATCH',
        url: `${app.forum.attribute('apiUrl')}/posts/${postId}`,
        body: { data: { type: 'posts', id: String(postId), attributes: { [field]: next } } },
      })
      .then(() => {
        this.saving = false;

        /*
         * The discussion carries the authoritative score and it is not in the
         * response to a post PATCH. Writing the optimistic value back onto the
         * model keeps everything else on the page in step with it — the sort,
         * the discussion page after a navigation, a second control for the
         * same discussion.
         */
        discussion.pushAttributes({ warrenScore: this.score, warrenUserVote: this.vote });

        m.redraw();
      })
      .catch((error) => {
        this.saving = false;
        this.score = before.score;
        this.vote = before.vote;

        m.redraw();

        throw error;
      });
  }
}
