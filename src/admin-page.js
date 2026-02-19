import {
  createElement as el,
  Fragment,
  render,
  useState,
} from "@wordpress/element";
import {
  Button,
  Notice,
  TabPanel,
  SearchControl,
  ToggleControl,
} from "@wordpress/components";

const data = window.wpVrtAdminData || {};

const apiToggleItem = async ({ type, id, disable }) => {
  const form = new FormData();
  form.append("action", "wp_vrt_toggle_item");
  form.append("_wpnonce", data.toggleNonce || "");
  form.append("type", type);
  form.append("id", id);
  form.append("disable", disable ? "1" : "0");

  const response = await fetch(data.ajaxUrl || "", {
    method: "POST",
    credentials: "same-origin",
    body: form,
  });

  if (!response.ok) {
    throw new Error("Failed to toggle item");
  }
};

const apiToggleAll = async ({ type, disable }) => {
  const form = new FormData();
  form.append("action", "wp_vrt_toggle_all");
  form.append("_wpnonce", data.toggleAllNonce || "");
  form.append("type", type);
  form.append("disable", disable ? "1" : "0");

  const response = await fetch(data.ajaxUrl || "", {
    method: "POST",
    credentials: "same-origin",
    body: form,
  });

  if (!response.ok) {
    throw new Error("Failed to toggle all");
  }
};

const apiToggleGroup = async ({ type, ids, disable }) => {
  const form = new FormData();
  form.append("action", "wp_vrt_toggle_group");
  form.append("_wpnonce", data.toggleAllNonce || "");
  form.append("type", type);
  form.append("disable", disable ? "1" : "0");
  ids.forEach((id) => form.append("ids[]", id));

  const response = await fetch(data.ajaxUrl || "", {
    method: "POST",
    credentials: "same-origin",
    body: form,
  });

  if (!response.ok) {
    throw new Error("Failed to toggle group");
  }
};

const Header = () =>
  el(
    "div",
    { className: "wp-vrt-header" },
    el(
      "div",
      { className: "wp-vrt-header-main" },
      el("h1", { className: "wp-vrt-title" }, "WP VRT"),
      el(
        "p",
        { className: "wp-vrt-subtitle" },
        "Virtual pages for visual regression testing.",
      ),
    ),
    el(
      "div",
      { className: "wp-vrt-actions" },
      el(
        Button,
        {
          href: data.discoveryUrl,
          target: "_blank",
          rel: "noopener",
          variant: "secondary",
        },
        "Discovery endpoint",
      ),
    ),
  );

const Section = ({ title, description, items, search, onToggleItem }) => {
  const filtered = items
    .filter((item) => {
      if (!search) return true;
      const value = (item.label + " " + item.url).toLowerCase();
      return value.includes(search.toLowerCase());
    })
    .sort((a, b) => {
      const labelA = (a.label || "").toLowerCase();
      const labelB = (b.label || "").toLowerCase();
      if (labelA < labelB) return -1;
      if (labelA > labelB) return 1;
      if (!!a.isDisabled === !!b.isDisabled) return 0;
      return a.isDisabled ? 1 : -1;
    });

  if (search && filtered.length === 0) {
    return el(
      "div",
      { className: "wp-vrt-section" },
      el(
        "div",
        { className: "wp-vrt-section-header" },
        el(
          "div",
          null,
          el("h2", { className: "components-heading" }, title),
          el("p", { className: "wp-vrt-section-desc" }, description),
        ),
        el("span", { className: "wp-vrt-pill" }, "0"),
      ),
      el("p", { className: "wp-vrt-empty" }, "No items found."),
    );
  }

  return el(
    "div",
    { className: "wp-vrt-section" },
    el(
      "div",
      { className: "wp-vrt-section-header" },
      el(
        "div",
        null,
        el("h2", { className: "components-heading" }, title),
        el("p", { className: "wp-vrt-section-desc" }, description),
      ),
      el("span", { className: "wp-vrt-pill" }, String(filtered.length)),
    ),
    filtered.length === 0
      ? el("p", { className: "wp-vrt-empty" }, "No items found.")
      : el(
          "div",
          { className: "wp-vrt-list wp-vrt-list-grid" },
          filtered.map((item) =>
            el(
              "div",
              {
                key: item.url,
                className: `wp-vrt-row${item.isDisabled ? " is-disabled" : ""}`,
              },
              el(
                "div",
                { className: "wp-vrt-row-main" },
                el(
                  "span",
                  { className: "wp-vrt-row-label" },
                  item.toggleUrl
                    ? el(
                        "div",
                        { className: "wp-vrt-toggle" },
                        el(ToggleControl, {
                          checked: !item.isDisabled,
                          label: "",
                          onChange: () => onToggleItem?.(item),
                        }),
                      )
                    : null,
                  el(
                    "a",
                    {
                      className: "wp-vrt-row-link",
                      href: item.url,
                      target: "_blank",
                      rel: "noopener",
                    },
                    item.label,
                  ),
                  item.isVrt
                    ? el(
                        "span",
                        { className: "wp-vrt-tag wp-vrt-tag-vrt" },
                        "VRT",
                      )
                    : null,
                  item.isDynamic
                    ? el("span", { className: "wp-vrt-tag" }, "Dynamic")
                    : null,
                ),
              ),
            ),
          ),
        ),
  );
};

const Groups = ({ groups, onToggleItem, onToggleGroup, search }) => {
  if (!groups || groups.length === 0) return null;

  return el(
    "div",
    { className: "wp-vrt-groups" },
    groups
      .map((group) => {
        const filteredItems = (group.items || []).filter((item) => {
          if (!search) return true;
          const value = (item.label + " " + item.url).toLowerCase();
          return value.includes(search.toLowerCase());
        });

        if (filteredItems.length === 0) {
          return null;
        }

        return el(
          "div",
          { className: "wp-vrt-group", key: group.title },
          el(
            "div",
            { className: "wp-vrt-group-header" },
            el(
              "div",
              null,
              el("h3", { className: "components-heading" }, group.title),
              group.description
                ? el(
                    "p",
                    { className: "wp-vrt-section-desc" },
                    group.description,
                  )
                : null,
            ),
            el(
              "div",
              { className: "wp-vrt-group-meta" },
              el(
                "span",
                { className: "wp-vrt-pill" },
                String(filteredItems.length),
              ),
              el(
                "div",
                { className: "wp-vrt-group-actions" },
                el(ToggleControl, {
                  className: "wp-vrt-bulk-toggle",
                  checked: !filteredItems.every((item) => item.isDisabled),
                  label: "Enabled",
                  onChange: (checked) =>
                    onToggleGroup?.(
                      { ...group, items: filteredItems },
                      !checked,
                    ),
                }),
              ),
            ),
          ),
          el(
            "div",
            { className: "wp-vrt-list wp-vrt-list-grid" },
            filteredItems
              .sort((a, b) => {
                const labelA = (a.label || "").toLowerCase();
                const labelB = (b.label || "").toLowerCase();
                if (labelA < labelB) return -1;
                if (labelA > labelB) return 1;
                if (!!a.isDisabled === !!b.isDisabled) return 0;
                return a.isDisabled ? 1 : -1;
              })
              .map((item) =>
                el(
                  "div",
                  {
                    key: item.url,
                    className: `wp-vrt-row${
                      item.isDisabled ? " is-disabled" : ""
                    }`,
                  },
                  el(
                    "div",
                    { className: "wp-vrt-row-main" },
                    el(
                      "span",
                      { className: "wp-vrt-row-label" },
                      item.toggleUrl
                        ? el(
                            "div",
                            { className: "wp-vrt-toggle" },
                            el(ToggleControl, {
                              checked: !item.isDisabled,
                              label: "",
                              onChange: () => onToggleItem?.(item),
                            }),
                          )
                        : null,
                      el(
                        "a",
                        {
                          className: "wp-vrt-row-link",
                          href: item.url,
                          target: "_blank",
                          rel: "noopener",
                        },
                        item.label,
                      ),
                    ),
                  ),
                ),
              ),
          ),
        );
      })
      .filter(Boolean),
  );
};

const TabContent = ({ section, onToggleItem, onToggleAll, onToggleGroup }) => {
  const [search, setSearch] = useState("");
  const [group, setGroup] = useState("all");

  return el(
    "div",
    {
      className: `wp-vrt-tab-panel${
        section.type ? ` wp-vrt-${section.type}` : ""
      }`,
    },
    el(
      "div",
      { className: "wp-vrt-toolbar" },
      el(SearchControl, {
        className: "wp-vrt-search",
        placeholder: `Search ${section.title.toLowerCase()}…`,
        value: search,
        onChange: (value) => setSearch(value),
      }),
      section.categories && section.categories.length > 0
        ? el(
            "div",
            { className: "wp-vrt-filter" },
            el(
              "button",
              {
                type: "button",
                className: `wp-vrt-chip${
                  (group || "all") === "all" ? " is-active" : ""
                }`,
                onClick: () => setGroup("all"),
              },
              "All",
            ),
            section.categories.map((category) =>
              el(
                "button",
                {
                  key: category.slug,
                  type: "button",
                  className: `wp-vrt-chip${
                    group === category.slug ? " is-active" : ""
                  }`,
                  onClick: () => setGroup(category.slug),
                },
                category.title,
              ),
            ),
          )
        : null,
      el(
        "div",
        { className: "wp-vrt-bulk" },
        section.bulkType || section.type
          ? el(ToggleControl, {
              className: "wp-vrt-bulk-toggle",
              checked: (section.items || []).some((item) => !item.isDisabled),
              label: "Enabled",
              onChange: (checked) => onToggleAll?.(section, !checked),
            })
          : null,
      ),
    ),
    section.categories && section.categories.length > 0
      ? el(
          "div",
          { className: "wp-vrt-grid" },
          el(Groups, {
            groups:
              group && group !== "all"
                ? section.categories.filter(
                    (category) => category.slug === group,
                  )
                : section.categories,
            onToggleItem,
            onToggleGroup,
            search,
          }),
        )
      : section.title === "Blocks" && section.groups
      ? el(
          "div",
          { className: "wp-vrt-grid" },
          el(Groups, {
            groups: section.groups,
            onToggleItem,
            onToggleGroup,
            search,
          }),
        )
      : el(
          "div",
          { className: "wp-vrt-grid" },
          el(Section, {
            title: section.title,
            description: section.description,
            items: section.items || [],
            search,
            onToggleItem,
          }),
        ),
  );
};

const App = () => {
  if (!data.baseUrl) {
    return el(
      Notice,
      { status: "warning", isDismissible: false },
      "WP VRT data not available.",
    );
  }

  const tabs = (data.sections || []).map((section) => {
    const stat = data.stats?.[section.title] ?? "";
    const title =
      stat !== ""
        ? el(
            "span",
            { className: "wp-vrt-tab-label" },
            section.title,
            el("span", { className: "wp-vrt-tab-count" }, String(stat)),
          )
        : section.title;
    return {
      name: section.title,
      title,
      className: "wp-vrt-tab",
    };
  });

  const [sections, setSections] = useState(() => data.sections || []);

  const handleToggleItem = async (item) => {
    const sectionIndex = sections.findIndex((section) =>
      section.items?.some((entry) => entry.url === item.url),
    );
    const fallbackType = sections[sectionIndex]?.type;
    const type = item.toggleType || fallbackType;
    const id = item.toggleUrl
      ? new URL(item.toggleUrl, window.location.href).searchParams.get("id")
      : null;
    if (!type || !id) return;

    const nextDisabled = !item.isDisabled;
    await apiToggleItem({ type, id, disable: nextDisabled });

    const updated = sections.map((section) => {
      const items = (section.items || []).map((entry) =>
        entry.url === item.url ? { ...entry, isDisabled: nextDisabled } : entry,
      );
      const groups = (section.groups || []).map((group) => ({
        ...group,
        items: (group.items || []).map((entry) =>
          entry.url === item.url
            ? { ...entry, isDisabled: nextDisabled }
            : entry,
        ),
      }));
      const categories = (section.categories || []).map((category) => ({
        ...category,
        items: (category.items || []).map((entry) =>
          entry.url === item.url
            ? { ...entry, isDisabled: nextDisabled }
            : entry,
        ),
      }));
      return { ...section, items, groups, categories };
    });

    setSections(updated);
  };

  const handleToggleAll = async (section, disable, typeOverride = "") => {
    const type = typeOverride || section.bulkType || section.type;
    if (!type) return;
    await apiToggleAll({ type, disable });

    const updated = sections.map((entry) => {
      if (entry.title !== section.title) return entry;
      const shouldUpdateItem = (item) => {
        if (typeOverride === "pattern-vrt") {
          return item.isVrt;
        }
        return true;
      };
      const items = (entry.items || []).map((item) =>
        shouldUpdateItem(item) ? { ...item, isDisabled: disable } : item,
      );
      const groups = (entry.groups || []).map((group) => ({
        ...group,
        items: (group.items || []).map((item) =>
          shouldUpdateItem(item) ? { ...item, isDisabled: disable } : item,
        ),
      }));
      const categories = (entry.categories || []).map((category) => ({
        ...category,
        items: (category.items || []).map((item) =>
          shouldUpdateItem(item) ? { ...item, isDisabled: disable } : item,
        ),
      }));
      return { ...entry, items, groups, categories };
    });

    setSections(updated);
  };

  const handleToggleGroup = async (group, disable) => {
    const ids = (group.items || [])
      .map((item) =>
        item.toggleUrl
          ? new URL(item.toggleUrl, window.location.href).searchParams.get("id")
          : null,
      )
      .filter(Boolean);
    if (ids.length === 0) return;

    await apiToggleGroup({ type: group.type || "block", ids, disable });

    const updated = sections.map((section) => {
      const items = (section.items || []).map((entry) => {
        const id = entry.toggleUrl
          ? new URL(entry.toggleUrl, window.location.href).searchParams.get(
              "id",
            )
          : null;
        if (!id || !ids.includes(id)) return entry;
        return { ...entry, isDisabled: disable };
      });
      const groups = (section.groups || []).map((entry) => ({
        ...entry,
        items: (entry.items || []).map((entryItem) => {
          const id = entryItem.toggleUrl
            ? new URL(
                entryItem.toggleUrl,
                window.location.href,
              ).searchParams.get("id")
            : null;
          if (!id || !ids.includes(id)) return entryItem;
          return { ...entryItem, isDisabled: disable };
        }),
      }));
      const categories = (section.categories || []).map((entry) => ({
        ...entry,
        items: (entry.items || []).map((entryItem) => {
          const id = entryItem.toggleUrl
            ? new URL(
                entryItem.toggleUrl,
                window.location.href,
              ).searchParams.get("id")
            : null;
          if (!id || !ids.includes(id)) return entryItem;
          return { ...entryItem, isDisabled: disable };
        }),
      }));
      return { ...section, items, groups, categories };
    });

    setSections(updated);
  };

  return el(
    Fragment,
    null,
    el(Header),
    el(TabPanel, { className: "wp-vrt-tabs", tabs }, (tab) => {
      const section = sections.find((s) => s.title === tab.name);
      if (!section) return null;

      return el(TabContent, {
        section,
        onToggleItem: handleToggleItem,
        onToggleAll: handleToggleAll,
        onToggleGroup: handleToggleGroup,
      });
    }),
  );
};

const root = document.getElementById("wp-vrt-app");
if (root) {
  render(el(App), root);
}
