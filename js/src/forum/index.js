import app from 'flarum/forum/app';
import Model from 'flarum/common/Model';
import Discussion from 'flarum/common/models/Discussion';
import Post from 'flarum/common/models/Post';

import decorateRow from './decorateRow';
import addRightRail from './addRightRail';
import addSidebarToggle from './addSidebarToggle';
import addSortOptions from './addSortOptions';
import addPostVotes from './addPostVotes';
import addThreading from './addThreading';
import dontTranslateAvatars from './dontTranslateAvatars';

// NOTE: the Admin extender is exported from js/src/admin/index.js and NOWHERE
// else. It calls app.extensionData, which exists only on the admin frontend —
// re-exporting it here runs it during forum boot and takes the whole forum
// down with "Cannot read properties of undefined (reading 'for')".

/*
 * The fields extend.php contributes to the discussion payload.
 *
 * Declaring them on the model is what makes `discussion.warrenScore()` work.
 * Without this they sit in the JSON, correct and complete, and stay invisible
 * to every component that asks for them — a whole feature that looks unwired
 * because nobody told the model the data had arrived.
 */
Discussion.prototype.warrenScore = Model.attribute('warrenScore');
Discussion.prototype.warrenRank = Model.attribute('warrenRank');
Discussion.prototype.warrenUserVote = Model.attribute('warrenUserVote');
Discussion.prototype.warrenImage = Model.attribute('warrenImage');
Discussion.prototype.warrenExcerpt = Model.attribute('warrenExcerpt');

// The same, for a comment. A post has no denormalised score column, so the
// number is two counted aggregates the frontend subtracts.
Post.prototype.warrenUpvotes = Model.attribute('warrenUpvotes');
Post.prototype.warrenDownvotes = Model.attribute('warrenDownvotes');
Post.prototype.warrenUserVote = Model.attribute('warrenUserVote');
Post.prototype.warrenCanVote = Model.attribute('warrenCanVote');

// The reply order and a depth per post, for the whole discussion at once.
Discussion.prototype.warrenThread = Model.attribute('warrenThread');

/*
 * Priority -100 so this initializer runs LAST.
 *
 * `extend()` wraps: each registration wraps the previous one, so the callback
 * registered last runs last. The rail needs that for
 * `PageStructure.containerItems`, where it adopts fof/forum-widgets-core's
 * side section out of the list. Registering first would mean looking for that
 * item before FoF had added it, and the forum would get a fourth column.
 */
app.initializers.add('ernestdefoe-warren', () => {
  decorateRow();
  addRightRail();
  addSidebarToggle();
  addSortOptions();
  addPostVotes();
  addThreading();
  dontTranslateAvatars();
}, -100);
