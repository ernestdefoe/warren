import app from 'flarum/forum/app';

/*
 * 🚨 The Admin extender is exported from js/src/admin/index.js and NOWHERE
 * else. It calls app.extensionData, which exists only on the admin frontend —
 * re-exporting it here runs it during forum boot and takes the whole forum
 * down. Cascade learned that the hard way; Warren starts with it written down.
 */

app.initializers.add('ernestdefoe-warren', () => {
  // Voting, threading and the row layout land here.
}, -100);
