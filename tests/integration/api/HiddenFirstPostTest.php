<?php

namespace ErnestDefoe\Warren\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Warren\Tests\integration\WarrenTestCase;
use Flarum\Api\Endpoint\Index;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Extend;
use PHPUnit\Framework\Attributes\Test;

/**
 * The row preview is the first post's content, so it must follow the first
 * post's visibility: a hidden or private opening post is not readable through
 * `warrenExcerpt` / `warrenImage` by anyone who could not open the post itself.
 *
 * Core scopes Warren's own `firstPost` eager load, so every test here runs
 * beside a neighbour extension that loads the relation UNSCOPED in an index
 * `after` hook. That is the case Warren has to hold on its own.
 */
class HiddenFirstPostTest extends WarrenTestCase
{
    private const TEXT = 'The opening post, with a picture.';
    private const IMAGE = 'https://example.com/a.png';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extend((new Extend\ApiResource(DiscussionResource::class))
            ->endpoint('index', fn (Index $endpoint) => $endpoint->after(fn ($context, $models) => $models->load('firstPost'))));

        // User 4 moderates (core's Mods group holds discussion.hidePosts).
        $this->prepareDatabase(['group_user' => [['user_id' => 4, 'group_id' => 4]]]);
    }

    /** @return array{0: string, 1: ?array} the raw body and discussion 1's attributes */
    private function row(?int $actor): array
    {
        $response = $this->send($this->request('GET', '/api/discussions', $actor ? ['authenticatedAs' => $actor] : []));
        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), $body);

        return [$body, collect(json_decode($body, true)['data'])->firstWhere('id', '1')['attributes'] ?? null];
    }

    private function assertPreviewWithheld(?int $actor, string $who): void
    {
        [$body, $row] = $this->row($actor);

        $this->assertNotNull($row, "$who still sees the discussion");
        $this->assertStringNotContainsString(self::TEXT, $body, "$who must not read the post's text");
        $this->assertStringNotContainsString(self::IMAGE, str_replace('\/', '/', $body), "$who must not get the post's image");
    }

    private function assertPreviewShown(?int $actor, string $who): void
    {
        [, $row] = $this->row($actor);

        $this->assertSame(self::IMAGE, $row['warrenImage'] ?? null, "$who gets the image");
        $this->assertStringStartsWith(self::TEXT, $row['warrenExcerpt'] ?? '', "$who gets the excerpt");
    }

    #[Test]
    public function a_hidden_first_post_is_previewed_only_to_its_author_and_moderators()
    {
        $this->database()->table('posts')->where('id', 1)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);

        $this->assertPreviewWithheld(null, 'A guest');
        $this->assertPreviewWithheld(3, 'A member');
        $this->assertPreviewShown(2, 'The author');
        $this->assertPreviewShown(4, 'A moderator');
        $this->assertPreviewShown(1, 'An admin');
    }

    #[Test]
    public function a_private_first_post_is_previewed_only_to_its_author()
    {
        $this->database()->table('posts')->where('id', 1)->update(['is_private' => true]);

        $this->assertPreviewWithheld(null, 'A guest');
        $this->assertPreviewWithheld(3, 'A member');
        $this->assertPreviewShown(2, 'The author');
    }

    #[Test]
    public function a_visible_first_post_is_previewed_to_everyone()
    {
        $this->assertPreviewShown(null, 'A guest');
        $this->assertPreviewShown(3, 'A member');
    }
}
