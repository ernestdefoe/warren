<?php

namespace ErnestDefoe\Warren\Tests\integration\api;

use ErnestDefoe\Warren\Tests\integration\WarrenTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Casting, changing and clearing a vote, and who may. */
class VotingTest extends WarrenTestCase
{
    private function post(int $id, ?int $actor): array
    {
        [$status, $body] = $this->call('GET', "/api/posts/$id", $actor);
        $this->assertSame(200, $status, json_encode($body));

        return $body['data']['attributes'];
    }

    private function discussionScore(int $id): array
    {
        $row = $this->database()->table('discussions')->where('id', $id)->first(['votes', 'hotness']);

        return [(int) $row->votes, (float) $row->hotness];
    }

    #[Test]
    public function a_member_upvotes_and_the_discussion_rises()
    {
        [$status, $body] = $this->vote(1, 3, 'up');
        $this->assertSame(200, $status, json_encode($body));

        $this->assertSame('Up', $this->database()->table('post_votes')->where(['post_id' => 1, 'user_id' => 3])->value('type'));

        [$votes, $hotness] = $this->discussionScore(1);
        $this->assertSame(1, $votes);
        $this->assertGreaterThan(0, $hotness);

        $mine = $this->post(1, 3);
        $this->assertSame(1, $mine['warrenUpvotes']);
        $this->assertSame(0, $mine['warrenDownvotes']);
        $this->assertSame('up', $mine['warrenUserVote']);
        $this->assertNull($this->post(1, 4)['warrenUserVote'], 'Someone else has not voted');
    }

    #[Test]
    public function the_same_arrow_again_clears_the_vote_and_the_other_switches_it()
    {
        $this->vote(1, 3, 'up');
        $this->vote(1, 3, 'up');
        $this->assertSame(0, $this->database()->table('post_votes')->count());
        $this->assertSame(0, $this->discussionScore(1)[0]);

        $this->vote(1, 3, 'up');
        $this->vote(1, 3, 'down');
        $this->assertSame(['Down'], $this->database()->table('post_votes')->pluck('type')->all());
        $this->assertSame(-1, $this->discussionScore(1)[0]);
        $this->assertLessThan(0, $this->discussionScore(1)[1]);

        $this->vote(1, 3, null);
        $this->assertSame(0, $this->database()->table('post_votes')->count());
    }

    #[Test]
    public function a_reply_vote_counts_on_the_post_but_not_the_discussion()
    {
        $this->vote(2, 4, 'up');

        $this->assertSame(1, $this->post(2, 4)['warrenUpvotes']);
        $this->assertSame(0, $this->discussionScore(1)[0]);
    }

    #[Test]
    public function a_guest_cannot_vote()
    {
        $this->assertContains($this->vote(1, null, 'up')[0], [401, 403]);
        $this->assertFalse($this->post(1, null)['warrenCanVote']);
        $this->assertSame(0, $this->database()->table('post_votes')->count());
    }

    #[Test]
    public function a_member_without_the_permission_cannot_vote()
    {
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'warren.vote')->delete();

        $this->assertSame(403, $this->vote(1, 3, 'up')[0]);
        $this->assertFalse($this->post(1, 3)['warrenCanVote']);
    }

    #[Test]
    public function a_hidden_post_cannot_be_voted_on()
    {
        $this->assertSame(403, $this->vote(4, 1, 'up')[0]);
    }

    #[Test]
    public function self_votes_can_be_switched_off()
    {
        $this->setting('ernestdefoe-warren.allow_self_votes', '0');

        $this->assertSame(403, $this->vote(1, 2, 'up')[0], 'The author');
        $this->assertSame(200, $this->vote(1, 3, 'up')[0]);
        $this->assertFalse($this->post(1, 2)['warrenCanVote']);
    }

    #[Test]
    public function only_up_or_down_is_accepted()
    {
        $this->assertSame(422, $this->vote(1, 3, 'sideways')[0]);
    }

    #[Test]
    public function a_thread_lists_votes_without_a_query_per_post()
    {
        foreach ([1, 2, 3] as $post) {
            $this->vote($post, 4, 'up');
        }

        // flarum/testing fails the request when the same query repeats.
        [$status, $body] = $this->call('GET', '/api/posts', 4, null, ['filter' => ['discussion' => '1']]);

        $this->assertSame(200, $status, json_encode($body));
        $byId = array_column(array_map(fn ($p) => ['id' => $p['id'], 'a' => $p['attributes']], $body['data']), 'a', 'id');
        $this->assertSame('up', $byId['2']['warrenUserVote']);
        $this->assertSame(1, $byId['3']['warrenUpvotes']);
    }
}
