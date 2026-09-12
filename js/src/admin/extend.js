import Admin from 'flarum/common/extenders/Admin';

/**
 * Warren's admin settings.
 *
 * In an Admin extender rather than inside `app.initializers.add` because
 * `app.extensionData` is registered by a core admin initializer that may not
 * have run when ours fires.
 */
export default [new Admin()];
