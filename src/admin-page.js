import { createElement as el, Fragment, render } from '@wordpress/element';
import { createElement as el, Fragment, render } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice, TabPanel, SearchControl } from '@wordpress/components';
import { useView } from '@wordpress/views';

const data = window.wpVrtAdminData || {};

const Header = () => (
  el('div', { className: 'wp-vrt-header' },
    el('div', null,
      el('h1', { className: 'wp-vrt-title' }, 'WP VRT'),
      el('p', { className: 'wp-vrt-subtitle' }, 'Virtual pages for visual regression testing.')
    ),
    el('div', { className: 'wp-vrt-actions' },
      el(Button, { href: data.discoveryUrl, target: '_blank', rel: 'noopener', variant: 'secondary' }, 'Discovery endpoint'),
      el(Button, { href: data.baseUrl, target: '_blank', rel: 'noopener', variant: 'primary' }, 'Open base URL')
    )
  )
);

const Stats = () => (
  el('div', { className: 'wp-vrt-stats' },
    Object.entries(data.stats || {}).map(([label, value]) => (
      el(Card, { className: 'wp-vrt-stat', key: label },
        el(CardBody, null,
          el('div', { className: 'wp-vrt-stat-value' }, String(value)),
          el('div', { className: 'wp-vrt-stat-label' }, label)
        )
      )
    ))
  )
);

const Section = ({ title, description, items, search }) => {
  const filtered = items.filter((item) => {
    if (!search) return true;
    const value = (item.label + ' ' + item.url).toLowerCase();
    return value.includes(search.toLowerCase());
  });

  return (
    el(Card, { className: 'wp-vrt-section' },
      el(CardHeader, null,
        el('div', { className: 'wp-vrt-section-header' },
          el('h2', { className: 'components-heading' }, title),
          el('span', { className: 'wp-vrt-pill' }, String(filtered.length))
        ),
        el('p', { className: 'wp-vrt-section-desc' }, description)
      ),
      el(CardBody, null,
        filtered.length === 0
          ? el('p', { className: 'wp-vrt-empty' }, 'No items found.')
          : el('div', { className: 'wp-vrt-list wp-vrt-list-grid' },
            filtered.map((item) => (
              el('div', { key: item.url, className: 'wp-vrt-row' },
                el('span', { className: 'wp-vrt-row-label' },
                  item.label,
                  item.isDynamic ? el('span', { className: 'wp-vrt-tag' }, 'Dynamic') : null
                ),
                el(Button, {
                  className: 'wp-vrt-row-action',
                  href: item.url,
                  target: '_blank',
                  rel: 'noopener',
                  icon: 'external',
                  label: 'Open preview',
                  isSmall: true,
                  variant: 'tertiary'
                })
              )
            ))
          )
      )
    )
  );
};

const Groups = ({ groups }) => {
  if (!groups || groups.length === 0) return null;

  return el('div', { className: 'wp-vrt-groups' },
    groups.map((group) => (
      el(Card, { className: 'wp-vrt-group', key: group.title },
        el(CardHeader, null,
          el('div', { className: 'wp-vrt-group-header' },
            el('h3', { className: 'components-heading' }, group.title),
            el('span', { className: 'wp-vrt-pill' }, String(group.items.length))
          ),
          group.description ? el('p', { className: 'wp-vrt-section-desc' }, group.description) : null
        ),
        el(CardBody, null,
          el('div', { className: 'wp-vrt-list wp-vrt-list-grid' },
            (group.items || []).map((item) => (
              el('div', { key: item.url, className: 'wp-vrt-row' },
                el('span', { className: 'wp-vrt-row-label' }, item.label),
                el(Button, {
                  className: 'wp-vrt-row-action',
                  href: item.url,
                  target: '_blank',
                  rel: 'noopener',
                  icon: 'external',
                  label: 'Open preview',
                  isSmall: true,
                  variant: 'tertiary'
                })
              )
            ))
          )
        )
      )
    ))
  );
};

const TabContent = ({ section }) => {
  const { view, updateView } = useView({
    kind: 'wp-vrt',
    name: 'admin',
    slug: section.title,
    defaultView: { search: '' }
  });

  return el('div', { className: 'wp-vrt-tab-panel' },
    el(SearchControl, {
      className: 'wp-vrt-search',
      placeholder: `Search ${section.title.toLowerCase()}…`,
      value: view.search || '',
      onChange: (value) => updateView({ ...view, search: value })
    }),
    section.title === 'Blocks' && section.groups
      ? el('div', { className: 'wp-vrt-grid' },
          el(Groups, { groups: section.groups })
        )
      : null,
    el('div', { className: 'wp-vrt-grid' },
      el(Section, {
        title: section.title,
        description: section.description,
        items: section.items || [],
        search: view.search || ''
      })
    )
  );
};

const App = () => {
  if (!data.baseUrl) {
    return el(Notice, { status: 'warning', isDismissible: false }, 'WP VRT data not available.');
  }

  const tabs = (data.sections || []).map((section) => ({
    name: section.title,
    title: section.title,
    className: 'wp-vrt-tab'
  }));

  return (
    el(Fragment, null,
      el(Header),
      el(Stats),
      el(TabPanel, { className: 'wp-vrt-tabs', tabs }, (tab) => {
        const section = (data.sections || []).find((s) => s.title === tab.name);
        if (!section) return null;

        return el(TabContent, { section });
      })
    )
  );
};

const root = document.getElementById('wp-vrt-app');
if (root) {
  render(el(App), root);
}
