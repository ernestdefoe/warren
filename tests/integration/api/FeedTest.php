<?php

namespace ErnestDefoe\Warren\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Warren\Tests\integration\WarrenTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The front page: its order, each row's preview and vote, and the forum payload. */
class FeedTest extends WarrenTestCase
{
    private function list(?int $actor = null, array $query = []): array
    {
        [$status, $body] = $this->call('GET', '/api/discussions', $actor, null, $query);
        $this->assertSame(200, $status, json_encode($body));

        return $body['data'];
    }

    #[Test]
    public function hot_is_the_default_order_and_newest_breaks_a_tie()
    {
        // No votes: every rank is 0, so the newer discussion comes first.
        $this->assertSame(['1', '2'], array_column($this->list(), 'id'));

        // An upvote on the older one lifts it above the newer one, unvoted.
        $this->vote(10, 4, 'up');
        $this->assertSame(['2', '1'], array_column($this->list(), 'id'));
    }

    #[Test]
    public function the_operator_can_put_the_front_page_back_on_latest()
    {
        $this->setting('ernestdefoe-warren.default_sort', 'latest');
        $this->app();
        $this->database()->table('discussions')->where('id', 2)->update(['hotness' => 99, 'last_posted_at' => Carbon::now()->addDay()]);

        $this->assertSame(['2', '1'], array_column($this->list(), 'id'), 'Core\'s own default: latest activity');

        $this->database()->table('discussions')->where('id', 2)->update(['last_posted_at' => Carbon::now()->subDays(5)]);
        $this->assertSame(['1', '2'], array_column($this->list(), 'id'), 'Not the hottest');
    }

    #[Test]
    public function hot_and_top_can_be_asked_for()
    {
        // The sorts the theme's own menu sends (warrenHotSort, warrenTopSort).
        $this->vote(10, 4, 'up');
        $this->vote(1, 4, 'down');

        $this->assertSame(['2', '1'], array_column($this->list(null, ['sort' => '-hotness,-createdAt']), 'id'));
        $this->assertSame(['2', '1'], array_column($this->list(null, ['sort' => '-votes']), 'id'));
    }

    #[Test]
    public function each_row_carries_its_score_vote_image_and_excerpt()
    {
        $this->vote(1, 3, 'up');

        $row = collect($this->list(3))->firstWhere('id', '1')['attributes'];

        $this->assertSame(1, $row['warrenScore']);
        $this->assertSame('up', $row['warrenUserVote']);
        $this->assertSame('https://example.com/a.png', $row['warrenImage']);
        $this->assertStringStartsWith('The opening post, with a picture.', $row['warrenExcerpt']);
        $this->assertArrayNotHasKey('warrenThread', $row, 'The thread is only built for a single discussion');
    }

    #[Test]
    public function the_feed_is_built_without_a_query_per_discussion()
    {
        $discussions = $posts = [];
        foreach (range(20, 30) as $id) {
            $discussions[] = ['id' => $id, 'title' => "D$id", 'created_at' => Carbon::now(), 'user_id' => 3, 'first_post_id' => $id, 'comment_count' => 1];
            $posts[] = ['id' => $id, 'discussion_id' => $id, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>Row '.$id.'</p></t>'];
        }
        $this->prepareDatabase([\Flarum\Discussion\Discussion::class => $discussions, \Flarum\Post\Post::class => $posts]);

        // flarum/testing fails the request when the same query repeats.
        $this->assertCount(13, $this->list(4));
    }

    #[Test]
    public function a_discussion_page_carries_its_reply_tree_of_visible_posts()
    {
        [$status, $body] = $this->call('GET', '/api/discussions/1', 4);

        $this->assertSame(200, $status);
        // Post 3 quotes post 2, so it nests under it; hidden post 4 is left out.
        $this->assertSame(['ids' => [1, 2, 3], 'depths' => [0, 0, 1]], $body['data']['attributes']['warrenThread']);
        $this->assertArrayNotHasKey('warrenExcerpt', $body['data']['attributes'], 'Previews are for the feed');
    }

    #[Test]
    public function the_forum_payload_carries_what_the_theme_needs()
    {
        [, $body] = $this->call('GET', '/api');
        $forum = $body['data']['attributes'];

        $this->assertSame('warrenVote', $forum['warrenVoteField']);
        $this->assertSame(2, $forum['warrenDiscussionCount']);
        $this->assertSame(4, $forum['warrenMemberCount']);
        $this->assertSame('-hotness,-createdAt', $forum['warrenHotSort']);
        $this->assertSame('-votes', $forum['warrenTopSort']);
        $this->assertFalse($forum['warrenHasHashtags']);
        $this->assertSame('hot', $forum['warrenDefaultSort']);
        $this->assertTrue($forum['warrenShowAbout']);
        $this->assertSame(24, $forum['warrenHashtagCount']);
        $this->assertTrue($forum['warrenCanVoteHere']);
    }

    #[Test]
    public function the_chosen_density_is_stamped_on_the_page_even_when_it_names_a_php_function()
    {
        $this->setting('ernestdefoe-warren.density', 'compact');

        $response = $this->send($this->request('GET', '/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-warren="compact"', (string) $response->getBody());
    }
}
