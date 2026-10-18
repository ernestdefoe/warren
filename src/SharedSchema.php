<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

/**
 * What shape the shared vote schema is in on THIS forum.
 *
 * 🚨 Every column Warren votes through belongs to fof/gamification, and
 * gamification has renamed two of them over the years. Both old and new names
 * are still in the wild, because its migrations only rename what an install
 * actually has. So there is no single right answer to "which column" — there
 * is only what this database says, and that is what this class is for.
 *
 * | what            | Warren alone      | gamification installed |
 * |-----------------|-------------------|------------------------|
 * | vote direction  | `post_votes.type` | `post_votes.value`     |
 * |                 | 'Up' / 'Down'     | 1 / -1                 |
 * | timestamps      | absent            | present (since 2022)   |
 * | discussion rank | `hotness`         | `trending` (since 2.x) |
 *
 * Warren creates the LEFT column on purpose. It is the 2019 shape, the only
 * one gamification's migration chain replays cleanly from — its 2020 migration
 * adds `value` unguarded, and its 2.x migration renames `hotness` unguarded, so
 * a forum that had already been given the modern names would fail to install
 * it at all. Warren pays for that by having to read both, here, once.
 */
class SharedSchema
{
    /**
     * 🚨 Asked once per CACHE lifetime, never per request.
     *
     * A per-process memo alone was not enough: PHP-FPM starts every request
     * with empty statics, so the "once" was once per request — two
     * information_schema queries on every discussion list and every
     * notification page, on forums that never cast a vote. Floxum's
     * performance test counts exactly that, against the extension's name.
     *
     * The answer only changes when a migration renames a column, and those run
     * on enable (Enabled is dispatched AFTER the migration, so the listener in
     * extend.php sees the new shape), on uninstall, or from `php flarum
     * migrate` — which is always followed by `cache:clear`, and that flushes
     * this key with everything else.
     */
    public const CACHE_KEY = 'ernestdefoe-warren.shared-schema';

    /** @var array{value: bool, timestamps: bool, trending: bool, userVotes: bool}|null */
    protected static ?array $shape = null;

    public function __construct(
        protected ConnectionInterface $db,
        protected Cache $cache
    ) {
    }

    /** @return array{value: bool, timestamps: bool, trending: bool, userVotes: bool} */
    protected function shape(): array
    {
        return static::$shape ??= $this->cache->rememberForever(self::CACHE_KEY, function (): array {
            $schema = $this->db->getSchemaBuilder();
            $votes = $schema->getColumnListing('post_votes');

            return [
                'value' => in_array('value', $votes, true),
                'timestamps' => in_array('value', $votes, true) && in_array('created_at', $votes, true),
                'trending' => $schema->hasColumn('discussions', 'trending'),
                'userVotes' => $schema->hasColumn('users', 'votes'),
            ];
        });
    }

    /** True once gamification's 2020 migration has converted the table. */
    public function hasValueColumn(): bool
    {
        return $this->shape()['value'];
    }

    /** The column a vote's direction is stored in. */
    public function voteColumn(): string
    {
        return $this->hasValueColumn() ? 'value' : 'type';
    }

    /** The column a discussion's time-decayed rank is stored in. */
    public function rankColumn(): string
    {
        return $this->shape()['trending'] ? 'trending' : 'hotness';
    }

    /**
     * Turn a direction into whatever this table stores.
     *
     * 🚨 'Up' and 'Down' are capitalised, and that is load-bearing.
     * Gamification's converter tests `$vote->type === 'Up'` exactly; a value it
     * does not recognise maps to 0, and a row that maps to 0 is DELETED rather
     * than kept. Lowercase here would mean every vote cast through Warren
     * vanished the day someone installed gamification — the exact outcome the
     * shared table exists to prevent, undone by one character.
     *
     * @param int $direction 1 or -1
     */
    public function encode(int $direction): int|string
    {
        if ($this->hasValueColumn()) {
            return $direction > 0 ? 1 : -1;
        }

        return $direction > 0 ? 'Up' : 'Down';
    }

    /** Turn whatever this table stores back into 1 or -1. */
    public function decode(mixed $stored): int
    {
        if ($this->hasValueColumn()) {
            return (int) $stored >= 0 ? 1 : -1;
        }

        return $stored === 'Up' ? 1 : -1;
    }

    /**
     * The column and value a vote in this direction is stored as, for a
     * `where()`.
     *
     * 🚨 Neither is a constant: see voteColumn(). Asked at call time rather
     * than at boot, so a forget() after a migration is seen by the next query.
     * A method, not a function declared in extend.php: a global function there
     * is declared again each time the extension boots in the same process,
     * which is a fatal error.
     *
     * @return array{string, int|string}
     */
    public function ofDirection(int $direction): array
    {
        return [$this->voteColumn(), $this->encode($direction)];
    }

    /**
     * SQL summing a set of vote rows to a score, in this table's shape.
     *
     * 🚨 Takes a qualifier rather than hardcoding a table name. Raw SQL is
     * passed through verbatim, so `post_votes.type` written here is wrong on
     * every forum with a table prefix and right only on the one it was tested
     * against. Callers that join pass an ALIAS they set on the query — the
     * builder prefixes the real table name and leaves the alias alone, so the
     * qualifier is prefix-proof by construction.
     *
     * The qualifier is not optional decoration on a join: `posts` has a `type`
     * column of its own, so an unqualified `type` is ambiguous and the query
     * simply errors.
     */
    public function sumExpression(string $qualifier = ''): string
    {
        $column = ($qualifier === '' ? '' : $qualifier.'.').$this->voteColumn();

        if ($this->hasValueColumn()) {
            return "COALESCE(SUM($column), 0)";
        }

        return "COALESCE(SUM(CASE WHEN $column = 'Up' THEN 1 ELSE -1 END), 0)";
    }

    /**
     * Timestamps arrived with gamification's 2022 migration. Warren's own table
     * has neither column — adding them early is what would break that
     * migration — and writing to a column that is not there is an insert that
     * fails, so the model has to ask rather than assume.
     */
    public function voteHasTimestamps(): bool
    {
        return $this->shape()['timestamps'];
    }

    /**
     * Does this forum track per-user vote totals?
     *
     * Only gamification creates `users.votes`. Warren maintains it when it is
     * there so a forum that adds gamification later finds its point totals
     * already correct, and never creates it, because a theme has no business
     * putting a scoring column on the user table.
     */
    public function tracksUserPoints(): bool
    {
        return $this->shape()['userVotes'];
    }

    /** After a schema change: an extension enabled, disabled or uninstalled. */
    public function forget(): void
    {
        static::$shape = null;
        $this->cache->forget(self::CACHE_KEY);
    }
}
