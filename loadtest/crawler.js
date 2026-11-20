// A crawler like the bots that walk every layered navigation and sort
// combination: no cookies, no think time, mostly URLs the page cache has
// never seen. Only point it at a store you run.
//
//   k6 run -e BASE_URL=https://store.example.com loadtest/crawler.js
//   k6 run -e BASE_URL=... -e VUS=10 -e DURATION=5m loadtest/crawler.js
import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, FILTERS, SORTS, categoryLinks, filterLinks, pick, absolute } from './lib.js';

export const options = {
  scenarios: {
    crawl: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 4),
      duration: __ENV.DURATION || '2m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
    'http_req_duration{page:crawl}': ['p(95)<60000'],
  },
  summaryTrendStats: ['med', 'p(95)', 'max', 'count'],
  noCookiesReset: false,
};

const UA = 'Mozilla/5.0 (compatible; DemoCrawler/1.0; load test)';

// Category URLs and the filter values on them, found once.
export function setup() {
  const home = http.get(BASE_URL + '/', { headers: { 'User-Agent': UA } });
  const categories = categoryLinks(home);
  const values = {};
  for (const category of categories.slice(0, 20)) {
    const res = http.get(absolute(category), { headers: { 'User-Agent': UA } });
    for (const link of filterLinks(res)) {
      const query = link.split('?')[1] || '';
      for (const pair of query.split('&')) {
        const [name, value] = pair.split('=');
        if (FILTERS.includes(name) && value) {
          (values[name] = values[name] || new Set()).add(value);
        }
      }
    }
  }
  return {
    categories,
    values: Object.fromEntries(Object.entries(values).map(([k, v]) => [k, [...v]])),
  };
}

export default function (data) {
  const names = Object.keys(data.values);
  const params = [];
  const used = new Set();
  const count = 1 + Math.floor(Math.random() * 3);
  for (let i = 0; i < count && names.length; i++) {
    const name = pick(names);
    if (!used.has(name)) {
      used.add(name);
      params.push(`${name}=${encodeURIComponent(pick(data.values[name]))}`);
    }
  }
  if (Math.random() < 0.7) {
    params.push(`product_list_order=${pick(SORTS)}`);
  }
  if (Math.random() < 0.3) {
    params.push(`product_list_dir=${pick(['asc', 'desc'])}`);
  }
  if (Math.random() < 0.4) {
    params.push(`p=${1 + Math.floor(Math.random() * 5)}`);
  }
  if (Math.random() < 0.2) {
    params.push(`product_list_limit=${pick([12, 24, 36])}`);
  }
  const url = `${pick(data.categories)}?${params.join('&')}`;
  const res = http.get(absolute(url), {
    headers: { 'User-Agent': UA },
    tags: { page: 'crawl' },
    jar: new http.CookieJar(),
  });
  check(res, { 'crawl is 200': (r) => r.status === 200 });
}
