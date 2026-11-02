<?php

namespace ErnestDefoe\Warren\Tests\integration\api;

use ErnestDefoe\Warren\SharedSchema;
use ErnestDefoe\Warren\Tests\integration\WarrenTestCase;
use ErnestDefoe\Warren\Vote;
use PHPUnit\Framework\Attributes\Test;

/**
 * fof/gamification 2.x retracts a vote by saving `value = 0`, not by deleting
 * the row. Such a row is "no vote", never an upvote.
 */
class RetractedVoteTest extends WarrenTestCase
{
    private function shape(bool $value): void
    {
        $this->app();
        (new \ReflectionClass(SharedSchema::class))->setStaticPropertyValue('shape', [
            'value' => $value, 'timestamps' => false, 'trending' => false, 'userVotes' => false,
        ]);
    }

    protected function tearDown(): void
    {
        (new \ReflectionClass(SharedSchema::class))->setStaticPropertyValue('shape', null);
        parent::tearDown();
    }

    #[Test]
    public function a_zero_value_row_is_no_vote()
    {
        $this->shape(true);

        $this->assertSame(1, (new Vote(['post_id' => 1, 'user_id' => 3]))->forceFill(['value' => 1])->direction());
        $this->assertSame(-1, (new Vote(['post_id' => 1, 'user_id' => 3]))->forceFill(['value' => -1])->direction());
        $this->assertSame(0, (new Vote(['post_id' => 1, 'user_id' => 3]))->forceFill(['value' => 0])->direction());
        $this->assertSame(0, (new Vote(['post_id' => 1, 'user_id' => 3]))->forceFill(['value' => '0'])->direction());
    }

    #[Test]
    public function an_unknown_type_is_no_vote()
    {
        $this->shape(false);

        $this->assertSame(1, (new Vote())->forceFill(['type' => 'Up'])->direction());
        $this->assertSame(-1, (new Vote())->forceFill(['type' => 'Down'])->direction());
        $this->assertSame(0, (new Vote())->forceFill(['type' => ''])->direction());
    }
}
