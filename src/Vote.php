<?php

/*
 * Warren — a Reddit-inspired theme for Flarum 2.
 */

namespace ErnestDefoe\Warren;

use Flarum\Database\AbstractModel;
use Flarum\Post\Post;
use Flarum\User\User;

/**
 * One person's vote on one post.
 *
 * 🚨 This maps `post_votes` — fof/gamification's table, deliberately. See the
 * migrations for why, and SharedSchema for the consequence: the direction lives
 * in `type` ('Up'/'Down') on a forum that has only run Warren, and in `value`
 * (1/-1) on one that has ever had gamification installed. Nothing outside this
 * class and VoteSchema should know that.
 *
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 */
class Vote extends AbstractModel
{
    protected $table = 'post_votes';

    protected $fillable = ['post_id', 'user_id'];

    public const UP = 1;
    public const DOWN = -1;

    /**
     * 🚨 Decided per instance, not declared.
     *
     * `created_at` and `updated_at` exist only once gamification's 2022
     * migration has run. Warren's own table has neither — deliberately, because
     * adding them early breaks that migration. Eloquent writing a column that
     * is not there is an insert that fails, so the answer has to come from the
     * table rather than from a constant.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->timestamps = resolve(SharedSchema::class)->voteHasTimestamps();
    }

    /** 1 or -1, whichever shape the row is stored in. */
    public function direction(): int
    {
        $schema = resolve(SharedSchema::class);

        return $schema->decode($this->getAttribute($schema->voteColumn()));
    }

    public function setDirection(int $direction): static
    {
        $schema = resolve(SharedSchema::class);

        $this->setAttribute($schema->voteColumn(), $schema->encode($direction));

        return $this;
    }

    public function isUpvote(): bool
    {
        return $this->direction() > 0;
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
