import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Icon from 'flarum/common/components/Icon';
import IndexPage from 'flarum/forum/components/IndexPage';

const STORAGE_KEY = 'warren.sidebarCollapsed';

/**
 * The round hamburger that folds the left rail away.
 *
 * The feed is the page, and on a laptop the left rail costs it 272px it could
 * be reading with. This is the control that gives that back.
 *
 * 🚨 The state lives on <html>, not in a component.
 *
 * The rail and the toggle are rendered by two different components that share
 * no ancestor worth threading state through, and the layout that has to react
 * is core's `.Page-container`. An attribute on the document element is read by
 * all three without any of them knowing about the others, and it is the same
 * mechanism the theme already uses for density.
 */
export default function addSidebarToggle() {
  apply(collapsed());

  extend('flarum/forum/components/IndexPage', 'viewItems', function (items) {
    items.add('warrenSidebarToggle', toggleView(), 100);
  });

  /*
   * 🚨 Re-applied on every page change, not set once at boot.
   *
   * The attribute is on <html>, which survives navigation — but a reader can
   * arrive on a discussion page first, where the rail does not exist, and the
   * collapsed class would then be describing a layout that is not on screen.
   * Re-stamping is cheap and keeps the document honest about the current page.
   */
  extend('flarum/forum/components/IndexPage', 'oncreate', function () {
    apply(collapsed());
  });
}

function toggleView() {
  const isCollapsed = collapsed();

  const label = app.translator.trans(
    isCollapsed
      ? 'ernestdefoe-warren.forum.sidebar.expand'
      : 'ernestdefoe-warren.forum.sidebar.collapse'
  );

  return (
    <button
      type="button"
      className="Warren-sidebarToggle"
      aria-label={label}
      aria-expanded={isCollapsed ? 'false' : 'true'}
      title={label}
      onclick={() => {
        const next = !collapsed();

        apply(next);
        store(next);
      }}
    >
      <Icon name="fas fa-bars" />
    </button>
  );
}

function collapsed() {
  return document.documentElement.getAttribute('data-warren-sidebar') === 'collapsed';
}

function apply(value) {
  document.documentElement.setAttribute('data-warren-sidebar', value ? 'collapsed' : 'open');
}

/**
 * 🚨 Every read and write is guarded.
 *
 * `localStorage` is not merely empty in a private window or with site data
 * blocked — the accessor itself THROWS. An unguarded read here would take down
 * the index page for exactly the readers least able to report why.
 */
function store(value) {
  try {
    if (value) {
      localStorage.setItem(STORAGE_KEY, '1');
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
  } catch (e) {
    // A preference that cannot be remembered is still a preference that works
    // for this page view.
  }
}

try {
  if (localStorage.getItem(STORAGE_KEY) === '1') {
    document.documentElement.setAttribute('data-warren-sidebar', 'collapsed');
  }
} catch (e) {
  // See store().
}
