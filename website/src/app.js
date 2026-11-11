(() => {
  "use strict";
  const BASE = document.documentElement.dataset.base || "";
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---------- Mobile navigation ---------- */
  const navToggle = $("[data-nav-toggle]");
  const siteNav = $("#site-nav");
  if (navToggle && siteNav) {
    navToggle.hidden = false;
    const setNav = (open) => {
      navToggle.setAttribute("aria-expanded", String(open));
      siteNav.classList.toggle("is-open", open);
    };
    navToggle.addEventListener("click", () =>
      setNav(navToggle.getAttribute("aria-expanded") !== "true"),
    );
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && siteNav.classList.contains("is-open")) {
        setNav(false);
        navToggle.focus();
      }
    });
    siteNav.addEventListener("click", (e) => {
      if (e.target.closest("a")) setNav(false);
    });
    document.documentElement.classList.add("js-nav");
  }

  /* ---------- Copy buttons ---------- */
  const announce = (msg) => {
    const live = $("#live-region");
    if (live) {
      live.textContent = "";
      setTimeout(() => (live.textContent = msg), 30);
    }
  };
  const copy = async (text, btn, label) => {
    try {
      await navigator.clipboard.writeText(text);
      const old = btn.textContent;
      btn.textContent = "Copied";
      announce(`${label || "Text"} copied to clipboard`);
      setTimeout(() => (btn.textContent = old), 1800);
    } catch {
      announce("Copy failed – please select the text manually");
    }
  };
  $$(".code").forEach((box) => {
    const b = document.createElement("button");
    b.type = "button";
    b.className = "copy";
    b.textContent = "Copy";
    b.setAttribute("aria-label", "Copy code to clipboard");
    b.addEventListener("click", () =>
      copy($("pre", box).innerText.replace(/\n$/, ""), b, "Code"),
    );
    box.append(b);
  });
  $$("[data-copy]").forEach((b) =>
    b.addEventListener("click", () => copy(b.dataset.copy, b, b.dataset.label)),
  );

  /* ---------- Share ---------- */
  $$("[data-share]").forEach((b) => {
    if (!navigator.share) return;
    b.hidden = false;
    b.addEventListener("click", () =>
      navigator
        .share({
          title: b.dataset.title,
          text: b.dataset.text,
          url: b.dataset.url,
        })
        .catch(() => {}),
    );
  });

  /* ---------- Search ---------- */
  let indexPromise;
  const loadIndex = () =>
    (indexPromise ||= fetch(`${BASE}/search-index.json`)
      .then((r) => r.json())
      .then((items) =>
        items.map((i) => ({
          ...i,
          hay: `${i.t} ${i.a || ""}`.toLowerCase(),
          body: (i.x || "").toLowerCase(),
        })),
      ));

  const tokens = (q) =>
    q
      .toLowerCase()
      .split(/[^a-z0-9äöüß]+/i)
      .filter(Boolean);

  const search = (items, q) => {
    const words = tokens(q);
    if (!words.length) return [];
    return items
      .map((i) => {
        let score = 0;
        for (const w of words) {
          const inTitle = i.hay.includes(w);
          const inBody = i.body.includes(w);
          if (!inTitle && !inBody) return { i, score: 0 };
          score += inTitle ? (i.t.toLowerCase().startsWith(w) ? 12 : 8) : 2;
          if (inBody) score += 1;
        }
        if (i.hay.includes(words.join(" ")) || i.hay.includes(words.join(":")))
          score += 10;
        if (i.k === "Command") score += 2;
        return { i, score };
      })
      .filter((r) => r.score > 0)
      .sort((a, b) => b.score - a.score)
      .map((r) => r.i);
  };

  const snippet = (item, q) => {
    const w = tokens(q)[0];
    const text = item.x || "";
    const at = w ? text.toLowerCase().indexOf(w) : -1;
    if (at < 0) return text.slice(0, 110);
    const start = Math.max(0, at - 40);
    return (start ? "…" : "") + text.slice(start, start + 130);
  };

  const resultItem = (item, q, id) => {
    const li = document.createElement("li");
    li.id = id;
    li.setAttribute("role", "option");
    const a = document.createElement("a");
    a.href = BASE + item.u;
    a.tabIndex = -1;
    const kind = document.createElement("span");
    kind.className = "result-kind";
    kind.textContent = item.k;
    const title = document.createElement("span");
    title.className = "result-title";
    title.textContent = item.t;
    const text = document.createElement("span");
    text.className = "result-text";
    text.textContent = snippet(item, q);
    a.append(kind, title, text);
    li.append(a);
    return li;
  };

  const mountSearch = (root) => {
    const input = $('input[type="search"]', root);
    const list = $('[role="listbox"]', root);
    const status = $("[data-search-status]", root);
    const form = $("form", root);
    let active = -1;
    let generation = 0;

    const close = () => {
      generation++;
      list.replaceChildren();
      list.hidden = true;
      input.setAttribute("aria-expanded", "false");
      input.removeAttribute("aria-activedescendant");
      active = -1;
    };
    const setActive = (n) => {
      const opts = $$('[role="option"]', list);
      opts.forEach((o, i) => o.setAttribute("aria-selected", String(i === n)));
      active = n;
      if (n >= 0) {
        input.setAttribute("aria-activedescendant", opts[n].id);
        opts[n].scrollIntoView({ block: "nearest" });
      } else input.removeAttribute("aria-activedescendant");
    };

    const update = async () => {
      const q = input.value.trim();
      if (q.length < 2) return close();
      const mine = ++generation;
      const items = await loadIndex();
      if (mine !== generation) return;
      const results = search(items, q).slice(0, 8);
      list.replaceChildren();
      if (!results.length) {
        const li = document.createElement("li");
        li.className = "search-empty";
        li.setAttribute("role", "presentation");
        li.textContent = `No results for “${q}”`;
        list.append(li);
      } else
        results.forEach((r, i) =>
          list.append(resultItem(r, q, `${list.id}-${i}`)),
        );
      list.hidden = false;
      input.setAttribute("aria-expanded", "true");
      setActive(-1);
      if (status)
        status.textContent = results.length
          ? `${results.length} results. Use up and down arrows to browse.`
          : "No results";
    };

    input.addEventListener("input", update);
    input.addEventListener(
      "focus",
      () => input.value.trim().length > 1 && update(),
    );
    input.addEventListener("keydown", (e) => {
      const n = $$('[role="option"]', list).length;
      if (e.key === "ArrowDown" && !n && input.value.trim().length > 1) {
        e.preventDefault();
        update();
      } else if (e.key === "ArrowDown" && n) {
        e.preventDefault();
        setActive((active + 1) % n);
      } else if (e.key === "ArrowUp" && n) {
        e.preventDefault();
        setActive((active - 1 + n) % n);
      } else if (e.key === "Enter" && active >= 0) {
        e.preventDefault();
        $("a", $$('[role="option"]', list)[active]).click();
      } else if (e.key === "Escape" && !list.hidden) {
        e.stopPropagation();
        close();
      }
    });
    document.addEventListener(
      "click",
      (e) => !root.contains(e.target) && close(),
    );
    form.addEventListener("submit", (e) => {
      if (active >= 0) e.preventDefault();
    });
  };
  $$("[data-search]").forEach(mountSearch);

  /* Search results page */
  const page = $("[data-search-page]");
  if (page) {
    const input = $('input[type="search"]', page);
    const out = $("#search-results");
    const status = $("#search-status");
    let generation = 0;
    const render = async () => {
      const q = input.value.trim();
      const mine = ++generation;
      if (!q) {
        out.replaceChildren();
        status.textContent = "";
        return;
      }
      const items = await loadIndex();
      if (mine !== generation) return;
      out.replaceChildren();
      const results = search(items, q);
      status.textContent = results.length
        ? `${results.length} result${results.length > 1 ? "s" : ""} for “${q}”`
        : `No results for “${q}”. Try a command name such as “theme build”.`;
      results.slice(0, 50).forEach((r) => {
        const li = resultItem(r, q, "");
        li.removeAttribute("role");
        li.removeAttribute("id");
        $("a", li).removeAttribute("tabindex");
        out.append(li);
      });
      history.replaceState(null, "", `?q=${encodeURIComponent(q)}`);
    };
    input.value = new URLSearchParams(location.search).get("q") || "";
    input.addEventListener("input", render);
    $("form", page).addEventListener("submit", (e) => {
      e.preventDefault();
      render();
    });
    render();
  }

  /* Search dialog + shortcuts */
  const dialog = $("#search-dialog");
  const open = () => {
    if (!dialog || dialog.open) return;
    dialog.showModal();
    $("input", dialog).focus();
  };
  $$("[data-open-search]").forEach((b) => b.addEventListener("click", open));
  $("[data-close-search]")?.addEventListener("click", () => dialog.close());
  dialog?.addEventListener("click", (e) => {
    if (e.target === dialog || e.target.closest?.("a[href]")) dialog.close();
  });
  document.addEventListener("keydown", (e) => {
    const typing =
      /^(input|textarea|select)$/i.test(document.activeElement?.tagName) ||
      document.activeElement?.isContentEditable;
    if (
      (e.key === "k" && (e.metaKey || e.ctrlKey)) ||
      (e.key === "/" && !typing && !e.metaKey && !e.ctrlKey)
    ) {
      e.preventDefault();
      open();
    }
  });
  if (/Mac|iPhone|iPad/.test(navigator.platform))
    $$("[data-mod]").forEach((k) => (k.textContent = "⌘"));

  /* TOC collapses on small screens */
  const toc = $("details.toc");
  if (toc && matchMedia("(max-width: 63.99rem)").matches) toc.open = false;
})();
