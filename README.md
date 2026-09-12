# Warren

**A Reddit-inspired theme for Flarum 2.** A vote gutter down the left of every
row, compact link rows with the thumbnail on the right, Hot / New / Top
sorting, and threaded comments.

Free and MIT, like [Cascade](https://github.com/ernestdefoe/cascade).

> [!NOTE]
> Early work in progress. The schema and the interop contract below are
> settled; the theme around them is being built.

---

## It shares fof/gamification's votes on purpose

Warren does **not** keep its own vote table. It reads and writes `post_votes`
and the `votes` / `hotness` columns on `discussions` — the same ones
[fof/gamification](https://github.com/FriendsOfFlarum/gamification) has used
since 2019.

That means:

- Start on Warren's voting, install gamification later — **every vote is still
  there**, and its ranks and notifications start working on the history you
  already have.
- Already using gamification — Warren shows those votes from the first page
  load, and stands its own voting down so you never see two vote controls on
  one post.
- Remove Warren — your votes are untouched.

There is no export, no import command and no reconciliation step, because there
is never a second copy of the data to reconcile.

`hotness` is the [published Reddit ranking
algorithm](https://github.com/reddit-archive/reddit), which is what
gamification implements too, so Hot means the same thing whichever is doing the
writing.

### The one thing to know

🚨 **Uninstalling fof/gamification drops the shared table.** Its own `down`
migration runs `dropIfExists('post_votes')` and drops the two discussion
columns. That is its behaviour, not something Warren can intercept — so if you
remove gamification, you lose the vote history, including votes cast through
Warren.

Warren's own migrations **never remove anything**. Disabling or uninstalling
Warren leaves every vote in place. The asymmetry is deliberate: a theme must
not be able to destroy a forum's data on its way out.

---

## Requirements

| | |
|---|---|
| Flarum | `2.0` or newer |
| PHP | `8.3+` |

Nothing else is required. `flarum/mentions` is strongly suggested — threaded
comments are rendered from the reply graph it already stores, so without it
every discussion renders flat.

---

## Contributing

If you are porting anything from Cascade, take it from **`main` as it stands
today**, not from an older copy. Several shell bugs were fixed there that are
easy to reintroduce by copying the earlier shape:

- `align-self: flex-start` inside a column flex container sizes the sidebar to
  its content — a 1842px rail inside a 768px page.
- A column gap of `0` leaves the rail flush against the feed, and padding
  cannot fix it because the nav takes its width from the sidebar variable.
- `overflow-y` on the rail makes it a clipping container on **both** axes, so
  it eats its own dropdowns.
- Touch targets below 44px on phones and tablets.
- Avatar initials get translated into words by the browser unless marked
  `notranslate` — a user called Ernest renders as "AND" on a Spanish page.
- The sidebar stacks above the feed on mobile unless it is explicitly ordered
  below it.

## Licence

MIT.
