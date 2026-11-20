// Response times by page type: each URL once, in order, at low concurrency.
// Run it right after flushing the cache for cold numbers, and again for
// numbers with the full page cache warm. The URLs come from a few category
// pages fetched in setup, which are not measured. Only point it at a store
// you run.
//
//   k6 run -e BASE_URL=https://store.example.com loadtest/pages.js
import http from 'k6/http';
import exec from 'k6/execution';
import { check } from 'k6';
import { BASE_URL, SEARCH_TERMS, categoryLinks, productLinks, filterLinks, absolute } from './lib.js';

const PER_TYPE = Number(__ENV.PER_TYPE || 20);
const TYPES = ['category', 'category_page', 'filter', 'search', 'product'];

export const options = {
  setupTimeout: '10m',
  scenarios: {
    pages: {
      executor: 'shared-iterations',
      vus: Number(__ENV.VUS || 2),
      iterations: PER_TYPE * TYPES.length,
      maxDuration: '30m',
    },
  },
  thresholds: Object.fromEntries(TYPES.map((t) => [`http_req_duration{page:${t}}`, ['p(95)<60000']])),
  summaryTrendStats: ['med', 'p(95)', 'max', 'count'],
};

// The same store gives the same list every time, so a second run requests
// the same URLs.
export function setup() {
  const home = http.get(BASE_URL + '/');
  const subcategories = categoryLinks(home).filter((l) => l.split('/').length === 3).sort();
  const visited = subcategories.slice(0, 6);
  const leaves = new Set();
  const products = new Set();
  const filters = new Set();
  for (const url of visited) {
    const res = http.get(absolute(url));
    categoryLinks(res).filter((l) => l.split('/').length === 4).forEach((l) => leaves.add(l));
    productLinks(res).forEach((l) => products.add(l));
    filterLinks(res).filter((l) => !/[?&]cat=/.test(l)).forEach((l) => filters.add(l));
  }
  const take = (list) => [...list].sort().slice(0, PER_TYPE);
  const urls = {
    category: take(new Set([...subcategories.slice(6), ...leaves])),
    category_page: subcategories.slice(6).sort().slice(0, PER_TYPE).map((l) => `${l}?p=2`),
    filter: take(filters),
    search: SEARCH_TERMS.slice(0, PER_TYPE).map((q) => `/catalogsearch/result/?q=${q}`),
    product: take(products),
  };
  return TYPES.flatMap((type) => urls[type].map((url) => ({ type, url })));
}

export default function (list) {
  const item = list[exec.scenario.iterationInTest];
  if (!item) {
    return;
  }
  const res = http.get(absolute(item.url), { tags: { page: item.type } });
  check(res, { 'page is 200': (r) => r.status === 200 });
}
