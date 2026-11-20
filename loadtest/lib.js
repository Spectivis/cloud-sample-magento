// Helpers shared by the k6 scripts. Point them only at a store you run.
import http from 'k6/http';
import { check } from 'k6';
import { parseHTML } from 'k6/html';

export const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8080').replace(/\/+$/, '');

// Words that appear in the generated product names.
export const SEARCH_TERMS = [
  'jacket', 'cotton', 'shirt', 'boots', 'linen', 'backpack', 'lamp', 'merino', 'sneakers', 'dress',
  'leather', 'tent', 'yoga', 'ceramic', 'hoodie', 'wallet', 'organic', 'rain', 'kids', 'running',
];

// Layered navigation and sorting parameters a crawler combines.
export const FILTERS = ['brand', 'color', 'size', 'material', 'pattern', 'season', 'price', 'cat'];
export const SORTS = ['position', 'name', 'price'];

export function get(url, page, params = {}) {
  const res = http.get(absolute(url), Object.assign({ tags: { page } }, params));
  check(res, { [`${page} is 200`]: (r) => r.status === 200 });
  return res;
}

export function absolute(url) {
  return url.startsWith('http') ? url : BASE_URL + url;
}

// Same-site links in a page, as paths with their query strings.
export function links(res) {
  if (!res.body) {
    return [];
  }
  const out = new Set();
  parseHTML(res.body).find('a[href]').each((_, a) => {
    const href = a.getAttribute('href');
    if (href && href.startsWith(BASE_URL)) {
      out.add(href.slice(BASE_URL.length) || '/');
    }
  });
  return [...out];
}

// Category pages: the menu's links, minus product and account pages.
export function categoryLinks(res) {
  return links(res).filter((l) => /^\/[a-z0-9-]+(\/[a-z0-9-]+)*\.html$/.test(l) && !/-dc-\d{6}\.html$/.test(l));
}

// Product pages: generated URL keys end in the SKU.
export function productLinks(res) {
  return links(res).filter((l) => /-dc-\d{6}\.html$/.test(l));
}

// Layered navigation links on a category or search page.
export function filterLinks(res) {
  return links(res).filter((l) => /[?&](brand|color|size|material|pattern|season|price|cat)=/.test(l));
}

export function pick(list) {
  return list[Math.floor(Math.random() * list.length)];
}

// Magento's built-in page cache serves forms with a placeholder key; the
// browser's script sends the form_key cookie, which the store adopts.
export function formKey(jar) {
  const key = 'k6' + Math.random().toString(36).slice(2, 14);
  jar.set(BASE_URL, 'form_key', key);
  return key;
}

// The options to add a configurable product to the cart: the first child
// that has a value for every attribute.
export function configurableOptions(body) {
  const doc = parseHTML(body);
  let config = null;
  doc.find('script[type="text/x-magento-init"]').each((_, s) => {
    if (config) {
      return;
    }
    const text = s.textContent();
    if (text.indexOf('spConfig') < 0) {
      return;
    }
    try {
      const json = JSON.parse(text);
      for (const target of Object.values(json)) {
        for (const component of Object.values(target)) {
          if (component && component.spConfig) {
            config = component.spConfig;
          }
        }
      }
    } catch (e) {
      // not the block we want
    }
  });
  if (!config) {
    return null;
  }
  const attributes = Object.values(config.attributes);
  const children = attributes[0].options.flatMap((o) => o.products);
  for (const child of children) {
    const chosen = {};
    for (const attribute of attributes) {
      const option = attribute.options.find((o) => o.products.includes(child));
      if (!option) {
        break;
      }
      chosen[`super_attribute[${attribute.id}]`] = option.id;
    }
    if (Object.keys(chosen).length === attributes.length) {
      return chosen;
    }
  }
  return null;
}
