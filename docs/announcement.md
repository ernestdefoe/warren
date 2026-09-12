# Warren — a Reddit-inspired theme for Flarum 2 (Built using AI)

Somebody asked for a Reddit-inspired theme, so here is one.

Compact posts on a dark ground, a vote pill under each one, image and text
previews, Hot / New / Top sorting, a collapsible rail, and comments that
actually nest.

![Warren's discussion list](https://raw.githubusercontent.com/ernestdefoe/warren/main/screenshots/feed.png)

Free, MIT, and on Packagist:

```bash
composer require ernestdefoe/warren
```

## Threading, with nothing to migrate

Replies nest under the comment they answer, with a collapse control on anything
that has children.

There is **no new table and no backfill**. `flarum/mentions` has been recording
the reply graph in `post_mentions_post` every time somebody quoted or replied to
a post since 2015 — Warren just reads it. A forum that has been running for
years threads its whole history the moment the theme is enabled, which is not
true of any design that starts recording a parent from today.

![Threaded comments](https://raw.githubusercontent.com/ernestdefoe/warren/main/screenshots/threading.png)

Without `flarum/mentions`, discussions render flat and nothing breaks.

## It shares fof/gamification's votes on purpose

This is the part I think is worth the post.

Warren keeps **no vote table of its own**. It reads and writes `post_votes` and
the score and ranking columns on `discussions` — the same ones
[fof/gamification](https://github.com/FriendsOfFlarum/gamification) has used
since 2019.

So:

- Start on Warren's voting and install gamification later — every vote is still
  there, and its ranks, notifications and point totals start working on the
  history you already have.
- Already running gamification — Warren shows those votes from the first page
  load and stands its own voting down, so you never get two vote controls on one
  post. The arrows you click are still Warren's; they write through
  gamification's endpoint.
- Remove Warren — your votes are untouched.

No export, no import command, no reconciliation step, because there is never a
second copy of the data.

### Why the schema looks a decade old

Warren creates `post_votes` in gamification's **2019** shape and calls the
ranking column `hotness`. Both are names gamification itself has moved on from:
it replaced `type` with an integer `value` in 2020, and renamed `hotness` to
`trending` in its Flarum 2 release.

Creating the modern names is the obvious choice and it is the wrong one.
Gamification's migrations are tracked per extension, so installing it later
replays its **whole** chain — and the two migrations that do this work are
unguarded. On a table that already had a `value` column, adding one is a
duplicate-column error; renaming a `hotness` that was never there is another.
Either would mean gamification **fails to install** on any forum that had run
Warren first.

The 2019 shape is the only one the chain replays cleanly from. Warren pays for
that by reading both shapes at runtime, and by ranking with gamification's
arithmetic rather than reddit's where the two differ — agreeing with the
neighbour matters more than being right alone on a column neither of us owns.

One thing to know either way: **uninstalling gamification drops that table.**
Its own `down` migration does `dropIfExists('post_votes')`. That is its
behaviour and not something Warren can intercept. Warren's own migrations never
remove anything.

## Requirements

Flarum 2.0+, PHP 8.3+. Nothing else is required.

`flarum/mentions` for threading, `flarum/tags` for the community mark on each
post, `fof/gamification` if you want ranks and notifications on the same votes,
and [Hashtags](https://github.com/ernestdefoe/hashtags) for the cloud in the
rail — all optional, all degrade quietly.

Only run one theme at a time; two compile into one stylesheet and fight.
[Wardrobe](https://github.com/ernestdefoe/wardrobe) exists if you want several
installed and a per-member picker.

---

Source, issues and the full write-up of the schema decision:
<https://github.com/ernestdefoe/warren>
