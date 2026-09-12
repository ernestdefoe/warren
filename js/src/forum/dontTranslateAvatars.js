import { extend } from 'flarum/common/extend';

/**
 * Tell the browser to leave avatar initials alone.
 *
 * 🚨 Not hypothetical. A user called Ernest renders as a circle containing
 * "E". Read a Spanish forum with Chrome's translation on and "e" is a Spanish
 * conjunction meaning "and" — so every avatar on the page turned into a circle
 * containing the word "AND", overflowing its own border. It was reported as a
 * broken stylesheet, which is exactly what it looked like.
 *
 * Any single letter is a word in some language: a, y, o, e, i. Initials are
 * identity, not prose.
 *
 * 🚨 The CLASS, not `translate="no"`. `HTMLElement.translate` is a BOOLEAN
 * property and Mithril sets known properties directly, so assigning the string
 * 'no' assigns something truthy and the element comes out `translate="yes"` —
 * the exact opposite, silently. `notranslate` is a plain class with no
 * property to collide with, and it is the opt-out Chrome's translator
 * documents.
 *
 * 🚨 Extended by module PATH rather than by importing Avatar, for the reason
 * in decorateRow.
 */
export default function dontTranslateAvatars() {
  extend('flarum/common/components/Avatar', 'view', function (vnode) {
    if (!vnode || typeof vnode !== 'object' || !vnode.attrs) return;

    const existing = vnode.attrs.className;

    vnode.attrs.className = existing ? existing + ' notranslate' : 'notranslate';
  });
}
