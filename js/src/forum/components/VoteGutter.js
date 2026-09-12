import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';

/**
 * The arrows and the score, down the left of a row.
 *
 * 🚨 One control, two backends.
 *
 * Warren's own vote endpoint stands down when fof/gamification is enabled —
 * two extensions recomputing the same denormalised totals is how a score ends
 * up disagreeing with the votes under it. But standing the write side down is
 * not standing the THEME down: a Reddit layout with somebody else's thumb
 * buttons in the middle of it is two themes, not one.
 *
 * So the control is always this one, and the field it writes changes
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
     * A vote is a round trip, and an arrow that waits for it feels broken on
     * any connection worse than a desk. These hold the answer we expect; the
     * response overwrites them, and a failure puts them back.
     */
    this.score = null;
    this.vote = null;
    this.saving = false;
  }

  view() {
    const discussion = this.attrs.discussion;

    const score = this.score === null ? discussion.warrenScore() || 0 : this.score;
    const vote = this.vote === null ? discussion.warrenUserVote() : this.vote;

    return (
      <div className="Warren-gutter">
        {this.arrow('up', vote === 'up')}
        <span className={classList('Warren-score', vote && `Warren-score--${vote}`)}>
          {this.format(score)}
        </span>
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
        onclick={() => this.cast(direction, active)}
      >
        <Icon name={direction === 'up' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'} />
      </button>
    );
  }

  /**
   * Reddit's abbreviation, and not only for width: a four-digit score in a
   * 40px column either overflows or shrinks the type below the row's smallest
   * readable size.
   */
  format(score) {
    if (score >= 10000) return `${(score / 1000).toFixed(0)}k`;
    if (score >= 1000) return `${(score / 1000).toFixed(1)}k`;

    return String(score);
  }

  cast(direction, active) {
    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
      return;
    }

    const discussion = this.attrs.discussion;
    const firstPost = discussion.firstPost();

    /*
     * 🚨 The vote is written to the POST, and on a list row the post is not
     * loaded — only its id is on the discussion. Without an id there is
     * nothing to PATCH, so the arrows stay put rather than firing a request
     * that would 404.
     */
    const postId = firstPost ? firstPost.id() : discussion.data.relationships?.firstPost?.data?.id;

    if (!postId) return;

    const before = { score: this.score, vote: this.vote };

    // Clicking the arrow you already chose clears the vote, which is what the
    // server does too — the two have to agree or the optimistic number is
    // wrong for the length of one round trip.
    const next = active ? null : direction;
    const delta = (next === 'up' ? 1 : next === 'down' ? -1 : 0)
      - (this.currentVote() === 'up' ? 1 : this.currentVote() === 'down' ? -1 : 0);

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
         * The discussion carries the authoritative score, and it is not in the
         * response to a post PATCH. Writing our optimistic value back onto the
         * model keeps the two in step for anything else reading it on this
         * page — a second gutter for the same discussion, the sort, the
         * discussion page after a navigation.
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

  currentVote() {
    return this.vote === null ? this.attrs.discussion.warrenUserVote() : this.vote;
  }
}
