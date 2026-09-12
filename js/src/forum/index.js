import app from 'flarum/forum/app';
import Model from 'flarum/common/Model';
import Discussion from 'flarum/common/models/Discussion';

import decorateRow from './decorateRow';
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

app.initializers.add('ernestdefoe-warren', () => {
  decorateRow();
  dontTranslateAvatars();
});
