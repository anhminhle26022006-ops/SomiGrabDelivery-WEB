/*
 * SOMI Customer: language (VI/EN) and currency (VND/USD).
 * Loaded before store.js so every customer script can call SomiI18n.t().
 *
 * Language: shares the "somi-language" key with app.js, so the language picked
 * on the public pages (index) carries over to the customer workspace and back.
 * Vietnamese is the source language: t("Đơn hàng") returns the Vietnamese text
 * as is, or its English entry from lang/en.js when the language is "en".
 *
 * Currency: "somi-currency" decides which price table new quotes and orders use.
 * Amounts are stored as integers in the smallest unit of the currency
 * (VND: 1 đồng, USD: 1 cent). An order keeps the currency it was created with,
 * so a paid price never changes when the customer switches currency later.
 */
(() => {
  "use strict";

  const LANG_KEY = "somi-language";
  const CURRENCY_KEY = "somi-currency";
  const LANGUAGES = {
    vi: { label: "Tiếng Việt", short: "VI", locale: "vi-VN" },
    en: { label: "English", short: "EN", locale: "en-US" }
  };
  const CURRENCIES = {
    VND: { label: "Việt Nam đồng", short: "VND", symbol: "₫", minor: 1, digits: 0 },
    USD: { label: "US dollar", short: "USD", symbol: "$", minor: 100, digits: 2 }
  };

  function read(key) {
    try { return localStorage.getItem(key); } catch { return null; }
  }
  function write(key, value) {
    try { localStorage.setItem(key, value); } catch { /* private mode */ }
  }

  // app.js falls back to "en" when nothing is saved; follow the same rule so both agree.
  const savedLang = read(LANG_KEY);
  const lang = LANGUAGES[savedLang] ? savedLang : "en";
  const savedCurrency = read(CURRENCY_KEY);
  const currency = CURRENCIES[savedCurrency] ? savedCurrency : "VND";
  const locale = LANGUAGES[lang].locale;
  const dict = lang === "en" ? window.SomiLangEN || {} : {};

  /* ---------- Formatting ---------- */
  const pad = (n) => String(n).padStart(2, "0");

  function money(amount, cur = currency) {
    const meta = CURRENCIES[cur] || CURRENCIES.VND;
    const value = Math.round(Number(amount) || 0) / meta.minor;
    return new Intl.NumberFormat(locale, {
      style: "currency", currency: cur, minimumFractionDigits: meta.digits, maximumFractionDigits: meta.digits
    }).format(value);
  }
  function dateTime(isoValue, withYear = false) {
    if (!isoValue) return "";
    const d = new Date(isoValue);
    if (lang === "en") {
      return d.toLocaleString("en-US", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit", ...(withYear ? { year: "numeric" } : {}) });
    }
    return `${pad(d.getHours())}:${pad(d.getMinutes())}, ${pad(d.getDate())}/${pad(d.getMonth() + 1)}${withYear ? "/" + d.getFullYear() : ""}`;
  }
  function dateOnly(isoValue) {
    const d = new Date(isoValue);
    if (lang === "en") return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
  }
  function timeOnly(isoValue) {
    const d = new Date(isoValue);
    if (lang === "en") return d.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" });
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }
  function number(value, digits = 1) {
    return new Intl.NumberFormat(locale, { maximumFractionDigits: digits }).format(value);
  }

  /* ---------- Translation ---------- */
  // Looks up the trimmed text and keeps the surrounding spaces, so " Đơn {0}" and "Đơn {0}" share one entry.
  function lookup(key) {
    const m = /^(\s*)([\s\S]*?)(\s*)$/.exec(key);
    const core = m[2];
    const has = (k) => Object.prototype.hasOwnProperty.call(dict, k);
    let found = core;
    if (has(core)) found = dict[core];
    else {
      const flat = core.replace(/\s+/g, " "); // text from PHP views may wrap over several lines
      if (has(flat)) found = dict[flat];
    }
    return m[1] + found + m[3];
  }
  function formatVar(value) {
    if (value === null || value === undefined) return "";
    if (typeof value === "object") {
      if ("m" in value) return money(value.m, value.c);
      if ("d" in value) return dateTime(value.d);
      if ("k" in value) return tx(value);
    }
    return String(value);
  }
  function fill(text, vars) {
    if (!vars) return text;
    return text.replace(/\{(\w+)\}/g, (all, name) => (name in vars ? formatVar(vars[name]) : all));
  }

  /*
   * t("Văn bản")                  -> translated text
   * t("Đơn {code}", { code })     -> translated text with named values
   * t`Đơn ${code} đã tạo`          -> tagged template, values become {0}, {1}, ...
   */
  function t(strings, ...values) {
    if (Array.isArray(strings) && strings.raw) {
      const key = strings.reduce((acc, part, i) => acc + part + (i < values.length ? `{${i}}` : ""), "");
      const vars = {};
      values.forEach((v, i) => { vars[i] = v; });
      return fill(lookup(key), vars);
    }
    return fill(lookup(String(strings ?? "")), values[0]);
  }

  /* A message saved in data (notifications, history notes) is kept in Vietnamese
   * with its values, and translated when it is shown. */
  function msg(k, v) { return v ? { k, v } : { k }; }
  function tx(value) {
    if (value === null || value === undefined || value === "") return "";
    if (typeof value === "object" && "k" in value) return fill(lookup(value.k), value.v);
    return lookup(String(value));
  }

  /* ---------- Static page text (PHP views) ---------- */
  const ATTRS = ["placeholder", "aria-label", "title", "alt"];
  function translateTree(root) {
    if (!root || lang === "vi") return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
      const text = node.nodeValue;
      if (!text.trim()) return;
      const out = lookup(text);
      if (out !== text) node.nodeValue = out;
    });
    root.querySelectorAll(ATTRS.map((a) => `[${a}]`).join(",")).forEach((el) => {
      ATTRS.forEach((a) => {
        const value = el.getAttribute(a);
        if (value && value.trim()) el.setAttribute(a, lookup(value));
      });
    });
  }

  function setLanguage(next) {
    if (!LANGUAGES[next]) return;
    write(LANG_KEY, next);
    window.location.reload();
  }
  function setCurrency(next) {
    if (!CURRENCIES[next]) return;
    write(CURRENCY_KEY, next);
    window.location.reload();
  }

  window.SomiI18n = {
    lang, currency, locale, LANGUAGES, CURRENCIES,
    t, msg, tx, money, dateTime, dateOnly, timeOnly, number,
    translateTree, setLanguage, setCurrency
  };

  // Defer scripts run before DOMContentLoaded, so the server-rendered shell is
  // translated before app.js looks at it.
  document.documentElement.lang = lang;
  translateTree(document.querySelector("[data-customer-app]"));
  if (lang === "en") document.title = lookup(document.title.replace(/ · SOMI$/, "")) + " · SOMI";
})();
