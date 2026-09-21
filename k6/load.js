/**
 * Load Testing for Liberal Drupal
 * if a url_list.json exists, please copy it to the folder the load.js file is.
 * docker run --rm -i -v ./url_list.json:/home/k6/url_list.json grafana/k6 run - <load.js
 * 
 * To have constant load you don't need stages.
 * executor: constant-vus,
 * duration: "30s",
 * vus: 200
 */

import http from 'k6/http';
import { sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { randomItem } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';
import { randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

// base URL. For local http://host.docker.internal:8090/
const BASE_URL = 'https://develop.unicorndomain.gr/';

// empty string for other environments other than dev
const BASE_ROOT = 'liberal';

// Define a global array of URLs.
const highFreQuencyUrls = [
    BASE_ROOT + '/liberal-markets',
    BASE_ROOT + '/katigories/politiki',
    BASE_ROOT + '/katigories/oikonomia',
    BASE_ROOT + '/katigories/epiheiriseis',
    BASE_ROOT + '/katigories/diethni-themata',
    BASE_ROOT + '/katigories/tehnologia',
    BASE_ROOT + '/katigories/ygeia',
    BASE_ROOT + '/katigories/aytokinito'
];

export const options = {
    discardResponseBodies: true,
    scenarios: {
        nodes: {
            executor: 'ramping-arrival-rate',
            startRate: 5,
            timeUnit: '1s',
            preAllocatedVUs: 100,
            maxVUs: 100,
            exec: 'nodes',
            stages: [
                {
                    target: 5,
                    duration: "10s"
                },
                {
                    target: 10,
                    duration: "10s"
                },
                {
                    target: 15,
                    duration: "10s"
                },
                {
                    target: 20,
                    duration: "60s"
                }
            ]
        },
        frontPage: {
            executor: 'ramping-arrival-rate',
            startRate: 10,
            timeUnit: '1s',
            preAllocatedVUs: 100,
            maxVUs: 100,
            exec: 'frontPage',
            stages: [
                {
                    target: 10,
                    duration: "10s"
                },
                {
                    target: 20,
                    duration: "10s"
                },
                {
                    target: 25,
                    duration: "10s"
                },
                {
                    target: 30,
                    duration: "60s"
                }
            ]
        },
        highFreQuencyUrls: {
            executor: 'ramping-arrival-rate',
            startRate: 5,
            timeUnit: '1s',
            preAllocatedVUs: 100,
            maxVUs: 100,
            exec: 'hfUrls',
            stages: [
                {
                    target: 5,
                    duration: "10s"
                },
                {
                    target: 10,
                    duration: "10s"
                },
                {
                    target: 15,
                    duration: "10s"
                },
                {
                    target: 20,
                    duration: "60s"
                }
            ]
        }
    },
};

const data = new SharedArray('List of URLs', function () {
    const data = JSON.parse(open('./url_list.json'));
    return data;
});

// here do actions after init. Only executes once.
export function setup () {

}

// This method generates 1 request to a random node url. File or with nid.
export function nodes () {
    // if there is a file the array will not be empty
    if(data.length > 0) {
        const element = data[Math.floor(Math.random() * data.length)];
        
        if(element.view_node.includes('localhost')) {
            http.get(element.view_node.replace('http://localhost:8090',BASE_URL));
        }
        else {
            http.get(element.view_node);
        }
    }
    else {
        http.get(BASE_URL + '/node/' + randomIntBetween(10000, 450000), {redirects: 1});
    }

    // for ramping the sleep is not needed because the delay is the timeUnit we set
    //sleep(0.1);
}

export function hfUrls () {
    const element = highFreQuencyUrls[Math.floor(Math.random() * highFreQuencyUrls.length)];
    http.get(BASE_URL + element);

    // for ramping the sleep is not needed because the delay is the timeUnit we set
    //sleep(0.1);
}

// This method generates requests to homepage
export function frontPage () {
    http.get(BASE_URL);

    // for ramping the sleep is not needed because the delay is the timeUnit we set
    //sleep(0.1);
}

// here do actions when everything finished. Only executes once.
export function teardown () {

}