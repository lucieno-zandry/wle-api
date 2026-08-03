import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    vus: 10,
    duration: '2s',
};

// Use the internal container name (default port 80)
const BASE_URL = 'http://localhost:8000/api';
const AUTH_TOKEN = '46|AvgU9vPSOU8pA6MfKzCBu0mljDnzF5Az3BqXStF7a2efa7b5';

const params = {
    headers: {
        'Authorization': `Bearer ${AUTH_TOKEN}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
};

function createCartItem() {
    const variantId = 1;
    const url = `${BASE_URL}/cart/create/${variantId}`;
    const payload = JSON.stringify({ count: 1 });

    const response = http.post(url, payload, params);

    if (response.status === 200 || response.status === 201) {
        const body = response.json();
        return body.cart_item.id;
    }

    console.error(`❌ Cart creation failed! Status: ${response.status}, Response: ${response.body}`);
    return null;
}

export default function () {
    const cartItemId = createCartItem();

    if (!cartItemId) return;

    const checkoutUrl = `${BASE_URL}/order/create`;
    const payload = JSON.stringify({
        cart_item_ids: [cartItemId],
        address_id: 1,            // <-- Check if Address #1 belongs to User #11 in your DB!
        shipping_method_id: 1,    // <-- Check if Shipping Method #1 exists in your DB!
        notes: "k6 Concurrency Test"
    });

    const response = http.post(checkoutUrl, payload, params);

    if (response.status === 200 || response.status === 201) {
        check(response, {
            'Allowed Checkout: Got expected 200/201': (r) => r.status === 200 || r.status === 201,
        });
    } else if (response.status === 403) {
        check(response, {
            'Prevented Oversell: Got expected 403': (r) => r.status === 403,
        });
    } else {
        check(response, {
            'Unexpected status code received': (r) => false,
        });
    }
    
    sleep(0.1);
}