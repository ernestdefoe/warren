import app from 'flarum/admin/app';
import Admin from 'flarum/common/extenders/Admin';

/**
 * Warren's admin settings.
 *
 * 🚨 An Admin extender, never `app.extensionData.for(...)` in an initializer.
 *
 * That was the Flarum 1 API and in Flarum 2 the property is GONE — not
 * deprecated, absent. It was renamed to `app.registry` and marked `@internal`,
 * so the extender is the supported way in.
 *
 * The failure is quiet: core wraps every initializer in a try/catch, so the
 * TypeError shows up as one toast saying the extension "failed to initialize"
 * and the settings simply never appear. Everything else on the page registers
 * normally, which is what makes it look like a missing feature rather than a
 * crash. `ernestdefoe/ridge` and `ernestdefoe/marginalia` are both sitting on
 * this right now.
 *
 * 🚨 And exported from js/src/admin/index.js and NOWHERE else. This file
 * imports `flarum/admin/app`, which does not exist on the forum frontend —
 * re-exporting it from the forum entry runs it during forum boot and takes
 * every page of the forum down, not just the admin panel.
 *
 * 🚨 Every field here has a reader, and extend.php names the reader beside the
 * default. A setting that is stored and never read is the commonest bug in
 * this codebase's history: built, worded, styled, saved, and doing nothing.
 */
const t = (key, params) => app.translator.trans(`ernestdefoe-warren.admin.settings.${key}`, params);

export default [
  new Admin()
    /*
     * Read by the document stamp in extend.php, which writes it to <html>
     * before the page paints, and by the [data-warren='compact'] token block.
     */
    .setting(() => ({
      setting: 'ernestdefoe-warren.density',
      label: t('density_label'),
      help: t('density_help'),
      type: 'select',
      options: {
        card: t('density_card'),
        compact: t('density_compact'),
      },
      default: 'card',
    }))

    /*
     * Read by the index endpoint's defaultSort AND by addSortOptions, which
     * has to put the same one first — the control captions itself from the
     * first entry, so the two disagreeing means a label that lies about what
     * is on screen.
     */
    .setting(() => ({
      setting: 'ernestdefoe-warren.default_sort',
      label: t('default_sort_label'),
      help: t('default_sort_help'),
      type: 'select',
      options: {
        hot: t('default_sort_hot'),
        latest: t('default_sort_latest'),
      },
      default: 'hot',
    }))

    // Read by addRightRail.
    .setting(() => ({
      setting: 'ernestdefoe-warren.show_about',
      label: t('show_about_label'),
      help: t('show_about_help'),
      type: 'boolean',
      default: true,
    }))

    /*
     * Read by the HashtagCloud widget.
     *
     * 🚨 Shown only when ernestdefoe/hashtags is enabled, because without it
     * the cloud never renders and this would be a control with nothing behind
     * it.
     */
    .setting(() => {
      if (!(app.data.extensions && app.data.extensions['ernestdefoe-hashtags'])) return null;

      return {
        setting: 'ernestdefoe-warren.hashtag_count',
        label: t('hashtag_count_label'),
        help: t('hashtag_count_help'),
        type: 'number',
        min: 1,
        max: 60,
        default: 24,
      };
    })

    // Read by ThreadTree, which clamps it to 1-20 before using it.
    .setting(() => ({
      setting: 'ernestdefoe-warren.thread_depth',
      label: t('thread_depth_label'),
      help: t('thread_depth_help'),
      type: 'number',
      min: 1,
      max: 20,
      default: 8,
    }))

    /*
     * Read by Warren's PostPolicy.
     *
     * 🚨 Hidden when fof/gamification is enabled, because Warren's policy is
     * not registered then — gamification owns voting, and it has a setting of
     * its own for exactly this. Showing both would be two switches for one
     * behaviour, and the one this page offers would be the dead one.
     */
    .setting(() => {
      if (app.data.extensions && app.data.extensions['fof-gamification']) return null;

      return {
        setting: 'ernestdefoe-warren.allow_self_votes',
        label: t('allow_self_votes_label'),
        help: t('allow_self_votes_help'),
        type: 'boolean',
        default: true,
      };
    })

    /*
     * The permission Warren's own voting answers to. Also absent on a
     * gamification forum, where its permission is the live one.
     */
    .permission(
      () => {
        if (app.data.extensions && app.data.extensions['fof-gamification']) return null;

        return {
          icon: 'fas fa-arrow-up',
          label: t('vote_permission_label'),
          permission: 'warren.vote',
        };
      },
      'start',
      95
    ),
];
