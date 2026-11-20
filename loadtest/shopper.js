// A shopper's visit: home, a category and its second page, a layered
// navigation filter, a search, a product page, add to cart and a guest
// checkout paid by check / money order. Only point it at a store you run.
//
//   k6 run -e BASE_URL=https://store.example.com loadtest/shopper.js
//   k6 run -e BASE_URL=... -e VUS=5 -e DURATION=5m loadtest/shopper.js
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import {
  BASE_URL, SEARCH_TERMS, get, pick, categoryLinks, productLinks, filterLinks, formKey, configurableOptions, absolute,
} from './lib.js';

const PAGES = ['home', 'category', 'category_page', 'filter', 'search', 'product', 'add_to_cart', 'cart', 'checkout', 'checkout_api'];

export const options = {
  scenarios: {
    shoppers: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 2),
      duration: __ENV.DURATION || '2m',
    },
  },
  thresholds: Object.assign(
    { http_req_failed: ['rate<0.02'], checks: ['rate>0.98'] },
    Object.fromEntries(PAGES.map((p) => [`http_req_duration{page:${p}}`, ['p(95)<60000']])),
  ),
  summaryTrendStats: ['med', 'p(95)', 'max', 'count'],
};

const think = () => sleep(Number(__ENV.THINK || 1));

export default function () {
  const jar = http.cookieJar();
  let products = [];

  group('browse', () => {
    const home = get('/', 'home');
    const categories = categoryLinks(home);
    check(categories, { 'menu has categories': (c) => c.length > 0 });
    think();

    const category = get(pick(categories), 'category');
    products = productLinks(category);
    check(products, { 'category lists products': (p) => p.length > 0 });
    think();

    const url = category.url.split('?')[0];
    get(`${url}?p=${1 + Math.ceil(Math.random() * 4)}`, 'category_page');
    think();

    const filters = filterLinks(category);
    if (filters.length) {
      const filtered = get(pick(filters), 'filter');
      products = products.concat(productLinks(filtered));
      think();
    }

    const search = get(`/catalogsearch/result/?q=${pick(SEARCH_TERMS)}`, 'search');
    const found = productLinks(search);
    check(found, { 'search finds products': (p) => p.length > 0 });
    products = products.concat(found);
    think();
  });

  if (!products.length) {
    return;
  }

  group('buy', () => {
    const productUrl = pick(products);
    const product = get(productUrl, 'product');
    const id = (product.body.match(/name="product" value="(\d+)"/) || [])[1];
    if (!id) {
      return;
    }
    think();

    const key = formKey(jar);
    const form = Object.assign({ product: id, qty: 1, form_key: key }, configurableOptions(product.body) || {});
    const added = http.post(absolute(`/checkout/cart/add/product/${id}/`), form, {
      tags: { page: 'add_to_cart' }, redirects: 0,
    });
    check(added, { 'add to cart redirects': (r) => r.status === 302 });
    const cart = http.get(absolute('/customer/section/load/?sections=cart'), { tags: { page: 'cart' } });
    const count = cart.status === 200 ? (cart.json('cart.summary_count') || 0) : 0;
    check(count, { 'cart has the product': (c) => c > 0 });
    if (!count) {
      return;
    }
    think();

    const checkout = get('/checkout/', 'checkout');
    const cartId = (checkout.body.match(/"quoteData":\{"entity_id":"([^"]+)"/) || [])[1];
    check(cartId, { 'checkout has a cart': (c) => !!c });
    if (!cartId) {
      return;
    }
    const api = `${BASE_URL}/rest/default/V1/guest-carts/${cartId}`;
    const json = { headers: { 'Content-Type': 'application/json' }, tags: { page: 'checkout_api' } };
    const address = {
      email: '', firstname: 'Demo', lastname: 'Shopper', street: ['1 Demo Street'], city: 'Los Angeles',
      region: 'California', region_id: 12, postcode: '90001', country_id: 'US', telephone: '5550100',
    };
    http.post(`${api}/estimate-shipping-methods`, JSON.stringify({ address }), json);
    think();
    const shipping = http.post(`${api}/shipping-information`, JSON.stringify({
      addressInformation: {
        shipping_address: address, billing_address: address,
        shipping_carrier_code: 'flatrate', shipping_method_code: 'flatrate',
      },
    }), json);
    check(shipping, { 'shipping saved': (r) => r.status === 200 });
    const order = http.post(`${api}/payment-information`, JSON.stringify({
      email: `shopper${__VU}-${__ITER}@example.com`, paymentMethod: { method: 'checkmo' }, billingAddress: address,
    }), json);
    check(order, { 'order placed': (r) => r.status === 200 && /^"?\d+"?$/.test(r.body) });
  });
  think();
}
