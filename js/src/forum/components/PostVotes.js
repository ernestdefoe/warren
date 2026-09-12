import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';

/**
 * The vote pill on a comment.
 *
 * Same control and same styles as the one on a list row — deliberately. A
 * score that looks one way in the feed and another inside the thread reads as
 * two different sites.
 *
 * The difference is where the number comes from. A discussion carries a
 * denormalised score because the front page sorts on it; a comment has no such
 * column, so the count arrives as two subquery aggregates and is subtracted
 * here.
 */
export default class PostVotes extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    // The optimistic copy. A vote is a round trip, and an arrow that waits for
    // one feels broken on any connection worse than a desk.
    this.delta = 0;
    this.vote = null;
    this.saving = false;
  }

  view() {
    const post = this.attrs.post;

    const base = (post.warrenUpvotes() || 0) - (post.warrenDownvotes() || 0);
    const score = base + this.delta;
    const vote = this.currentVote();

    return (
      <div className={classList('Warren-votes', vote && `Warren-votes--${vote}`)}>
        {this.arrow('up', vote === 'up')}
        <span className="Warren-score">{score}</span>
        {this.arrow('down', vote === 'down')}
      </div>
    );
  }

  arrow(direction, active) {
    const label = app.translator.trans(`ernestdefoe-warren.forum.vote.${direction}`);

    return (
      <button
        type="button"
        className={classList('Warren-arrow', `Warren-arrow--${direction}`, active && 'Warren-arrow--active')}
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

  currentVote() {
    return this.vote === null ? this.attrs.post.warrenUserVote() : this.vote;
  }

  cast(direction, active) {
    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
      return;
    }

    const post = this.attrs.post;
    const was = this.currentVote();
    const before = { delta: this.delta, vote: this.vote };

    // Clicking the arrow you already chose clears the vote, which is what the
    // server does too — the two have to agree or the optimistic number is
    // wrong for the length of a round trip.
    const next = active ? null : direction;

    this.delta +=
      (next === 'up' ? 1 : next === 'down' ? -1 : 0) -
      (was === 'up' ? 1 : was === 'down' ? -1 : 0);

    this.vote = next;
    this.saving = true;

    const field = app.forum.attribute('warrenVoteField') || 'warrenVote';

    app
      .request({
        method: 'PATCH',
        url: `${app.forum.attribute('apiUrl')}/posts/${post.id()}`,
        body: { data: { type: 'posts', id: String(post.id()), attributes: { [field]: next } } },
      })
      .then((payload) => {
        this.saving = false;

        /*
         * 🚨 The counts come back in the response, so the optimistic delta is
         * thrown away rather than kept.
         *
         * Keeping both would double-count: the store merges the server's new
         * totals into the model and the delta would still be sitting on top of
         * them. The one moment the number is authoritative is the moment to
         * stop guessing.
         */
        app.store.pushPayload(payload);

        this.delta = 0;
        this.vote = null;

        m.redraw();
      })
      .catch((error) => {
        this.saving = false;
        this.delta = before.delta;
        this.vote = before.vote;

        m.redraw();

        throw error;
      });
  }
}
