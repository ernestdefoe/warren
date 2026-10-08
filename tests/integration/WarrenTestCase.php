<?php

namespace ErnestDefoe\Warren\Tests\integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

/**
 * Users: 1 admin, 2 author, 3 and 4 members.
 * Discussion 1 by 2: posts 1 (first), 2 by 3, 3 by 4 quoting 2, 4 hidden.
 * Discussion 2 by 3: post 10, older.
 */
abstract class WarrenTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-mentions', 'ernestdefoe-warren');

        $post = fn (int $id, int $discussion, int $number, int $user, string $text, array $extra = []) => $extra + [
            'id' => $id, 'discussion_id' => $discussion, 'number' => $number, 'created_at' => Carbon::now(), 'user_id' => $user,
            'type' => 'comment', 'content' => '<t><p>'.$text.'</p></t>',
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'third', 'email' => 'third@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'fourth', 'email' => 'fourth@machine.local', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Newer', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 3],
                ['id' => 2, 'title' => 'Older', 'created_at' => Carbon::now()->subDays(3), 'last_posted_at' => Carbon::now()->subDays(3), 'user_id' => 3, 'first_post_id' => 10, 'comment_count' => 1],
            ],
            Post::class => [
                $post(1, 1, 1, 2, 'The opening post, with a picture.', ['content' => '<r><p>The opening post, with a picture. <IMG src="https://example.com/a.png"><s>![](</s>https://example.com/a.png<e>)</e></IMG></p></r>']),
                $post(2, 1, 2, 3, 'A reply'),
                $post(3, 1, 3, 4, 'A reply to the reply'),
                $post(4, 1, 4, 3, 'Hidden', ['hidden_at' => Carbon::now()]),
                $post(10, 2, 1, 3, 'Something older'),
            ],
            'post_mentions_post' => [['post_id' => 3, 'mentions_post_id' => 2]],
        ]);
    }

    /** @return array{0: int, 1: mixed} */
    protected function call(string $method, string $path, ?int $actor = null, ?array $json = null, array $query = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($json !== null) {
            $options['json'] = $json;
        }

        $request = $this->request($method, $path, $options)->withQueryParams($query);

        if (! $actor && $method !== 'GET') {
            $session = $this->send($this->request('GET', '/api'));
            $request = $this->request($method, $path, $options + ['cookiesFrom' => $session])->withHeader('X-CSRF-Token', $session->getHeaderLine('X-CSRF-Token'));
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    protected function vote(int $post, ?int $actor, ?string $direction): array
    {
        return $this->call('PATCH', "/api/posts/$post", $actor, ['data' => ['type' => 'posts', 'id' => (string) $post, 'attributes' => ['warrenVote' => $direction]]]);
    }
}
