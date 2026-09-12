import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';

/**
 * A weighted cloud of the forum's most-used hashtags.
 *
 * 🚨 Reads ernestdefoe/hashtags' own `/api/hashtags` endpoint rather than
 * counting anything itself. That extension owns what a hashtag is, which posts
 * count toward one, and who is allowed to see it. Reimplementing any of that
 * here would be a second answer to the same question, and the two would drift
 * — with this one quietly showing tags a reader is not allowed to see.
 *
 * Rendered only when that extension is enabled; see widgetItems().
 */
export default class HashtagCloud extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.tags = [];

    app
      .request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/hashtags`,
        /*
         * Over-fetched and ranked below rather than trusting a sort parameter.
         * The endpoint's sort names are that extension's business and could be
         * renamed; `postCount` is in the payload either way. A rename upstream
         * then costs ordering, not the whole widget.
         */
        params: { page: { limit: 60 } },
      })
      .then((response) => {
        this.tags = rank(response?.data ?? [], count());
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        /*
         * A failed widget must not take the page with it. The rail renders
         * nothing, which is what a forum with no hashtags yet sees anyway.
         */
        this.tags = [];
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    // Nothing to say is better than an empty panel with a heading on it.
    if (!this.loading && !this.tags.length) return null;

    return (
      <div className="Warren-card Warren-card--hashtags">
        <div className="Warren-card-head">
          {app.translator.trans('ernestdefoe-warren.forum.rail.hashtags')}
        </div>

        <div className="Warren-card-body">
          {this.loading ? (
            <LoadingIndicator display="block" size="small" />
          ) : (
            <div className="Warren-hashtags">{this.tags.map(hashtagView)}</div>
          )}
        </div>
      </div>
    );
  }
}

/** How many hashtags the admin wants in the cloud. */
function count() {
  const configured = parseInt(app.forum.attribute('warrenHashtagCount'), 10);

  return Number.isFinite(configured) && configured > 0 ? Math.min(configured, 60) : 24;
}

/**
 * Rank by use and assign each hashtag a weight of 1-5.
 *
 * 🚨 Weighted by RANK, not by raw count. A cloud sized from the count is
 * unreadable on a real forum: one hashtag with 400 posts and forty with 3
 * gives one enormous word and a field of identical small ones. Bucketing by
 * position spreads the sizes evenly however lopsided the counts are, which is
 * the only reason a cloud communicates anything at a glance.
 */
function rank(data, limit) {
  const tags = data
    .map((row) => ({
      name: row.attributes?.name ?? '',
      posts: row.attributes?.postCount ?? 0,
    }))
    .filter((t) => t.name)
    .sort((a, b) => b.posts - a.posts)
    .slice(0, limit);

  return tags.map((tag, index) => ({
    ...tag,
    weight: tags.length < 2 ? 3 : 5 - Math.floor((index / tags.length) * 5),
  }));
}

function hashtagView(tag) {
  return (
    <Link
      className={`Warren-hashtag Warren-hashtag--w${tag.weight}`}
      href={app.route('hashtag', { name: tag.name })}
      key={tag.name}
      title={app.translator.trans('ernestdefoe-warren.forum.rail.hashtags_count', { count: tag.posts })}
    >
      #{tag.name}
    </Link>
  );
}
