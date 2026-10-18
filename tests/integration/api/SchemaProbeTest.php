<?php

namespace ErnestDefoe\Warren\Tests\integration\api;

use ErnestDefoe\Warren\SharedSchema;
use ErnestDefoe\Warren\Tests\integration\WarrenTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * 🚨 Pages that never touch a vote must not pay for Warren's schema question.
 *
 * PHP-FPM starts every request with empty statics, so a per-process memo is
 * not a cache. This clears the memo between requests the way a new FPM
 * request would, and asserts the second request reads the answer from the
 * cache rather than from information_schema / sqlite_master.
 */
class SchemaProbeTest extends WarrenTestCase
{
    #[Test]
    public function the_shared_schema_is_probed_once_not_per_request()
    {
        $this->call('GET', '/api/discussions');

        foreach (['/api', '/api/discussions', '/api/posts'] as $path) {
            foreach ([null, 3] as $actor) {
                // A new FPM request: every static memo starts empty.
                $class = new \ReflectionClass(SharedSchema::class);
                foreach (array_keys($class->getStaticProperties()) as $memo) {
                    $class->setStaticPropertyValue($memo, null);
                }

                $probes = [];
                $this->database()->listen(function ($query) use (&$probes) {
                    if (preg_match('/information_schema|sqlite_master|pragma_table|table_info/i', $query->sql)) {
                        $probes[] = $query->sql;
                    }
                });

                $this->call('GET', $path, $actor, null, $path === '/api/posts' ? ['filter' => ['discussion' => '1']] : []);

                $this->assertSame([], $probes, "$path as ".($actor ? 'member' : 'guest'));
            }
        }
    }
}
