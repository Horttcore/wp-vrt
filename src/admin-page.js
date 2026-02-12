import { createElement as el, Fragment, render, useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice, TabPanel, SearchControl, ToggleControl } from '@wordpress/components';
import { useView } from '@wordpress/views';

const data = window.wpVrtAdminData || {};

const apiToggleItem = async ({ type, id, disable }) => {
  const form = new FormData();
  form.append('action', 'wp_vrt_toggle_item');
  form.append('_wpnonce', data.toggleNonce || '');
  form.append('type', type);
  form.append('id', id);
  form.append('disable', disable ? '1' : '0');

  const response = await fetch(data.ajaxUrl || '', {
    method: 'POST',
    credentials: 'same-origin',
    body: form
  });

  if (!response.ok) {
    throw new Error('Failed to toggle item');
  }
};

const apiToggleAll = async ({ type, disable }) => {
  const form = new FormData();
  form.append('action', 'wp_vrt_toggle_all');
  form.append('_wpnonce', data.toggleAllNonce || '');
  form.append('type', type);
  form.append('disable', disable ? '1' : '0');

  const response = await fetch(data.ajaxUrl || '', {
    method: 'POST',
    credentials: 'same-origin',
    body: form
  });

  if (!response.ok) {
    throw new Error('Failed to toggle all');
  }
};

const Header = () => (
  el('div', { className: 'wp-vrt-header' },
    el('div', null,
      el('h1', { className: 'wp-vrt-title' }, 'WP VRT'),
      el('p', { className: 'wp-vrt-subtitle' }, 'Virtual pages for visual regression testing.')
    ),
    el('div', { className: 'wp-vrt-actions' },
      el(Button, { href: data.showDisabledUrl, variant: 'secondary' }, data.showDisabled ? 'Hide disabled' : 'Show disabled'),
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

const Section = ({ title, description, items, search, onToggleItem }) => {
  const filtered = items.filter((item) => {
    if (!search) return true;
    const value = (item.label + ' ' + item.url).toLowerCase();
    return value.includes(search.toLowerCase());
  }).sort((a, b) => {
    if (!!a.isDisabled === !!b.isDisabled) return 0;
    return a.isDisabled ? 1 : -1;
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
              el('div', { key: item.url, className: `wp-vrt-row${item.isDisabled ? ' is-disabled' : ''}` },
                el('span', { className: 'wp-vrt-row-label' },
                  item.label,
                  item.isDynamic ? el('span', { className: 'wp-vrt-tag' }, 'Dynamic') : null,
                  item.isDisabled ? el('span', { className: 'wp-vrt-tag wp-vrt-tag-disabled' }, 'Disabled') : null
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
                }),
                item.toggleUrl ? el('div', { className: 'wp-vrt-row-toggle' },
                  el(ToggleControl, {
                    checked: !item.isDisabled,
                    label: item.isDisabled ? 'Disabled' : 'Enabled',
                    onChange: () => onToggleItem?.(item)
                  })
                ) : null
              )
            ))
          )
      )
    )
  );
};

const Groups = ({ groups, onToggleItem }) => {
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
            (group.items || []).sort((a, b) => {
              if (!!a.isDisabled === !!b.isDisabled) return 0;
              return a.isDisabled ? 1 : -1;
            }).map((item) => (
              el('div', { key: item.url, className: `wp-vrt-row${item.isDisabled ? ' is-disabled' : ''}` },
                el('span', { className: 'wp-vrt-row-label' },
                  item.label,
                  item.isDisabled ? el('span', { className: 'wp-vrt-tag wp-vrt-tag-disabled' }, 'Disabled') : null
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
                }),
                item.toggleUrl ? el('div', { className: 'wp-vrt-row-toggle' },
                  el(ToggleControl, {
                    checked: !item.isDisabled,
                    label: item.isDisabled ? 'Disabled' : 'Enabled',
                    onChange: () => onToggleItem?.(item)
                  })
                ) : null
              )
            ))
          )
        )
      )
    ))
  );
};

const TabContent = ({ section, onToggleItem, onToggleAll }) => {
  const { view, updateView } = useView({
    kind: 'wp-vrt',
    name: 'admin',
    slug: section.title,
    defaultView: { search: '' }
  });

  return el('div', { className: 'wp-vrt-tab-panel' },
    el('div', { className: 'wp-vrt-toolbar' },
      el(SearchControl, {
        className: 'wp-vrt-search',
        placeholder: `Search ${section.title.toLowerCase()}…`,
        value: view.search || '',
        onChange: (value) => updateView({ ...view, search: value })
      }),
      el('div', { className: 'wp-vrt-bulk' },
        section.type ? el(Button, { variant: 'secondary', onClick: () => onToggleAll?.(section, true) }, 'Disable all') : null,
        section.type ? el(Button, { variant: 'secondary', onClick: () => onToggleAll?.(section, false) }, 'Enable all') : null
      )
    ),
    section.title === 'Blocks' && section.groups
      ? el('div', { className: 'wp-vrt-grid' },
          el(Groups, { groups: section.groups, onToggleItem })
        )
      : null,
    el('div', { className: 'wp-vrt-grid' },
      el(Section, {
        title: section.title,
        description: section.description,
        items: section.items || [],
        search: view.search || '',
        onToggleItem
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

  const [sections, setSections] = useState(() => data.sections || []);

  const handleToggleItem = async (item) => {
    const sectionIndex = sections.findIndex((section) => section.items?.some((entry) => entry.url === item.url));
    const type = sections[sectionIndex]?.type;
    const id = item.toggleUrl ? new URL(item.toggleUrl, window.location.href).searchParams.get('id') : null;
    if (!type || !id) return;

    const nextDisabled = !item.isDisabled;
    await apiToggleItem({ type, id, disable: nextDisabled });

    const updated = sections.map((section) => {
      const items = (section.items || []).map((entry) =>
        entry.url === item.url ? { ...entry, isDisabled: nextDisabled } : entry
      );
      const groups = (section.groups || []).map((group) => ({
        ...group,
        items: (group.items || []).map((entry) =>
          entry.url === item.url ? { ...entry, isDisabled: nextDisabled } : entry
        )
      }));
      return { ...section, items, groups };
    });

    setSections(updated);
  };

  const handleToggleAll = async (section, disable) => {
    if (!section.type) return;
    await apiToggleAll({ type: section.type, disable });

    const updated = sections.map((entry) => {
      if (entry.title !== section.title) return entry;
      const items = (entry.items || []).map((item) => ({ ...item, isDisabled: disable }));
      const groups = (entry.groups || []).map((group) => ({
        ...group,
        items: (group.items || []).map((item) => ({ ...item, isDisabled: disable }))
      }));
      return { ...entry, items, groups };
    });

    setSections(updated);
  };

  return (
    el(Fragment, null,
      el(Header),
      el(Stats),
      el(TabPanel, { className: 'wp-vrt-tabs', tabs }, (tab) => {
        const section = sections.find((s) => s.title === tab.name);
        if (!section) return null;

        return el(TabContent, { section, onToggleItem: handleToggleItem, onToggleAll: handleToggleAll });
      })
    )
  );
};

const root = document.getElementById('wp-vrt-app');
if (root) {
  render(el(App), root);
}
