<?php

use Flarum\Database\Migration;

/**
 * Members may vote out of the box; guests may not.
 *
 * Group 3 is Flarum's Member group. Guests (2) are omitted deliberately rather
 * than forgotten: a vote is attributed to a user id, so there is no coherent
 * way for an unauthenticated visitor to cast one — the control simply does not
 * render for them.
 *
 * Idempotent and group-aware: Flarum's helper skips a row that already exists
 * and skips a group that does not, so this is safe on a forum with a
 * customised group table.
 */
return Migration::addPermissions([
    'warren.vote' => 3,
]);
