# Warren

**A Reddit-inspired theme for Flarum 2.** Compact posts on a dark ground, a
vote pill under each one, image and text previews, Hot / New / Top sorting, a
collapsible rail, and genuinely threaded comments.

Free and MIT, like [Cascade](https://github.com/ernestdefoe/cascade).

![Warren's discussion list](screenshots/feed.png)

---

## What it does

- **Posts, not rows.** No cards and no borders — each post sits on the ground,
  separated by a hairline, and lifts on hover.
- **A vote pill** at the foot of every post and every comment, which fills with
  the vote's colour once you pick a side.
- **Previews.** A post leads with its first image, or with its opening lines
  when it has none. Never both: a row carrying a picture *and* three lines of
  prose is taller than two rows that each carry one.
- **Hot and Top** in the sort control, with Hot as the default.
- **A collapsible left rail** that hands its width back to the feed.
- **An About panel**, and a **hashtag cloud** when
  [Hashtags](https://github.com/ernestdefoe/hashtags) is installed.
- **Threaded comments**, below.

---

## Threading, with nothing to migrate

Replies nest under the comment they answer, with a collapse control on any post
that has children.

🚨 **There is no new table and no backfill.** `flarum/mentions` has recorded the
reply graph in `post_mentions_post` every time somebody quoted or replied to a
post since 2015. Warren reads that, so a forum that has been running for years
threads its **whole history** the moment the theme is enabled — which is not
true of any design that starts recording a parent from today.

Without `flarum/mentions` every discussion simply renders flat.

---

## It shares fof/gamification's votes on purpose

Warren does **not** keep its own vote table. It reads and writes `post_votes`
and the score and ranking columns on `discussions` — the same ones
[fof/gamification](https://github.com/FriendsOfFlarum/gamification) has used
since 2019.

That means:

- Start on Warren's voting, install gamification later — **every vote is still
  there**, and its ranks, notifications and per-user point totals start working
  on the history you already have. It recomputes those totals by summing the
  whole table, so nothing needs backfilling.
- Already using gamification — Warren shows those votes from the first page
  load, and stands its own voting down so you never see two vote controls on
  one post.
- Remove Warren — your votes are untouched.

There is no export, no import command and no reconciliation step, because there
is never a second copy of the data to reconcile.

### Why the schema looks a decade old

Warren creates `post_votes` in gamification's **2019** shape — `id`, `post_id`,
`user_id`, `type` — and calls the discussion's ranking column `hotness`. Both
are names gamification itself has since moved on from: it replaced `type` with
an integer `value` in 2020, and renamed `hotness` to `trending` in its Flarum 2
release.

Creating the modern names would be the obvious choice and it is the wrong one.
Gamification's migrations are tracked per extension, so installing it later
replays its **whole** chain — and the two migrations that do this work are
unguarded. On a table that already had a `value` column, adding one is a
duplicate-column error; renaming a `hotness` that was never there is another.
Either would mean gamification simply **fails to install** on any forum that
had run Warren first.

The 2019 shape is the only one the chain replays cleanly from. Every later
gamification migration then does exactly what it was written to do, to Warren's
rows: add `value`, convert them, drop `type`, add the timestamps, add the
foreign keys, add the unique index, rename the ranking column. A forum that
switches ends up with a table indistinguishable from one gamification built
itself.

Warren pays for that by reading both shapes at runtime, which is a dozen lines
in one class, and by ranking with gamification's arithmetic rather than
reddit's where the two differ. Agreeing with the neighbour matters more than
being right alone for a column neither extension owns by itself.

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

## Settings

| | |
|---|---|
| **Row density** | Roomy, or the classic dense list. |
| **Default view** | Hot, or Latest. |
| **Show the About panel** | Off gives the width back to the feed. |
| **Hashtags in the cloud** | Only when the Hashtags extension is enabled. |
| **Maximum reply indent** | How far a reply can move right before it stops. Deeper replies still sit under their parent. |
| **Let people vote on their own posts** | Only when gamification is *not* installed; it has its own. |

---

## Requirements

| | |
|---|---|
| Flarum | `2.0` or newer |
| PHP | `8.3+` |

Nothing else is required.

| Suggested | What it adds |
|---|---|
| `flarum/mentions` | Threaded comments. Without it, discussions render flat. |
| `fof/gamification` | Ranks, vote notifications and per-user points on the same votes. |
| `flarum/tags` | The community mark and name on every post. |
| `ernestdefoe/hashtags` | The hashtag cloud in the rail. |

---

## Installation

```bash
composer require ernestdefoe/warren
php flarum cache:clear
```

Then enable **Warren** in the admin panel.

> [!TIP]
> Only one theme should be enabled at a time — two compile into one stylesheet
> and fight. [Wardrobe](https://github.com/ernestdefoe/wardrobe) exists if you
> want several installed and a per-member picker.

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
