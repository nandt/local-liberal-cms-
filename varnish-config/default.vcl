vcl 4.1;

import std;

# Default backend definition. Points to Apache, normally.
# Apache is in this config on port 8080.
backend default {
    .host = "liberal-cms-drupal";
    .port = "80";
    .first_byte_timeout = 300s;
}

# Access control list for PURGE requests.
# Here you need to put the IP address of your web server
acl purge {
    "127.0.0.1";
    "liberal-cms-drupal";
}

# Respond to incoming requests.
sub vcl_recv {

    # Add an X-Forwarded-For header with the client IP address.
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
        }
        else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    # Remove empty query string parameters
    # e.g.: www.example.com/index.html?
    if (req.url ~ "\?$") {
        set req.url = regsub(req.url, "\?$", "");
    }

    # Remove the proxy header to mitigate the httpoxy vulnerability
    # See https://httpoxy.org/
    unset req.http.proxy;

    # Add X-Forwarded-Proto header when using https
    if (!req.http.X-Forwarded-Proto) {
        if(std.port(server.ip) == 443 || std.port(server.ip) == 8443) {
            set req.http.X-Forwarded-Proto = "https";
        } else {
            set req.http.X-Forwarded-Proto = "http";
        }
    }

    if (req.method == "URIBAN") {
        ban("req.http.host == " + req.http.host + " && req.url == " + req.url);
        # Throw a synthetic page so the request won't go to the backend.
        return (synth(200, "Ban added."));
    }

    # Only allow PURGE requests from IP addresses in the 'purge' ACL.
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(403, "Not allowed."));
        }
        return (purge);
    }

    # Only allow BAN requests from IP addresses in the 'purge' ACL.
    if (req.method == "BAN") {
        # Check against the ACLs.
        if (!client.ip ~ purge) {
        return (synth(403, "Not allowed."));
        }

        # Logic for the ban, using the Cache-Tags header else use a url approach
        if (req.http.Cache-Tags) {
            # Switch spaces to a regular expresson "or".
            # This is to use the "Varnish Bundled Purger" instead of sending tags one by one
            # One by one should work too though
            set req.http.Cache-Tags = regsuball(req.http.Cache-Tags, ",", "\|");

            ban("obj.http.Cache-Tags ~ " + req.http.Cache-Tags);
        }
        else {
            if(req.url == "/frontpage") {
                ban("req.url == /");
            }
            else {
                ban("req.url ~ " + req.url);
            }
        }

        # Throw a synthetic page so the request won't go to the backend.
        return (synth(200, "Ban added."));
    }

    # Only cache GET and HEAD requests (pass through POST requests other than node loader).
    if (req.method != "GET" && 
        req.method != "HEAD" &&
        !(req.url ~ "/api/node-loader/.*$")) {
        return (pass);
    }

    # Don't cache healthchecks so we don't have stale results.
    if (req.url ~ "^/health$") {
        return(pipe);
    }

    # Pass through any administrative or AJAX-related paths.
    if (req.url ~ "^/status\.php$" ||
        req.url ~ "^/update\.php$" ||
        req.url ~ "^/admin$" ||
        req.url ~ "^/admin/.*$" ||
        req.url ~ "^/flag/.*$" ||
        req.url ~ "^.*/ajax/.*$" ||
        req.url ~ "^.*/ahah/.*$" ||
        req.url ~ "^/cron.php") {
           return (pass);
    }

    # Always cache the following file types for all users if not coming from the private file system.
    if (req.url ~ "(?i)/(modules|themes|files)/.*\.(webp|png|gif|jpeg|jpg|ico|swf|css|js|flv|f4v|mov|mp3|mp4|pdf|doc|ttf|eot|svg|woff|eof|ppt)(\?[a-z0-9]+)?$") {
        unset req.http.Cookie;
        # Set header so we know to remove Set-Cookie later on.
        set req.http.X-static-asset = "True";
    }

    # Remove all cookies that Drupal doesn't need to know about. We explicitly
    # list the ones that Drupal does need, the SESS and NO_CACHE. If, after
    # running this code we find that either of these two cookies remains, we
    # will pass as the page cannot be cached.
    if (req.http.Cookie) {
        # 1. Append a semi-colon to the front of the cookie string.
        # 2. Remove all spaces that appear after semi-colons.
        # 3. Match the cookies we want to keep, adding the space we removed
        #    previously back. (\1) is first matching group in the regsuball.
        # 4. Remove all other cookies, identifying them by the fact that they have
        #    no space after the preceding semi-colon.
        # 5. Remove all spaces and semi-colons from the beginning and end of the
        #    cookie string.
        set req.http.Cookie = ";" + req.http.Cookie;
        set req.http.Cookie = regsuball(req.http.Cookie, "; +", ";");
        set req.http.Cookie = regsuball(req.http.Cookie, ";(SESS[a-z0-9]+|SSESS[a-z0-9]+|NO_CACHE)=", "; \1=");
        set req.http.Cookie = regsuball(req.http.Cookie, ";[^ ][^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^[; ]+|[; ]+$", "");

        if (req.http.Cookie == "") {
            # If there are no remaining cookies, remove the cookie header. If there
            # aren't any cookie headers, Varnish's default behavior will be to cache
            # the page.
            unset req.http.Cookie;
        }
        else {
            # If there is any cookies left (a session or NO_CACHE cookie), do not
            # cache the page. Pass it on to Apache directly.
            return (pass);
        }
    }

    # we are returning cacheable to set ESI capability in header
    set req.http.Surrogate-Capability = "abc=ESI/1.0";
    
    # Cacheable, Lookup in cache.
    return (hash);
}

# Set a header to track a cache HITs and MISSes.
sub vcl_deliver {
    # Remove ban-lurker friendly custom headers when delivering to client.
    unset resp.http.X-Url;
    unset resp.http.X-Host;

    # Comment these for easier Drupal cache tag debugging in development.
    unset resp.http.Cache-Tags;
    unset resp.http.X-Drupal-Cache-Contexts;
    unset resp.http.X-Esi;

    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT";
    }
    else {
        set resp.http.X-Varnish-Cache = "MISS";
    }
}

# Instruct Varnish what to do in the case of certain backend responses (beresp).
sub vcl_backend_response {

   /** Enable ESI if requested on this page */
   if (beresp.http.X-Esi) {
     set beresp.do_esi = true;
     /** Avoid cache on Browser side */
     unset beresp.http.ETag;
     unset beresp.http.Last-Modified;
   }

    if (bereq.url ~ "^/modules/custom/liberal_advertising_tools/js/lat_all_amp.js") {
        set beresp.http.Access-Control-Allow-Origin = "https://www-liberal-gr.cdn.ampproject.org";
        set beresp.http.Access-Control-Allow-Methods = "GET, OPTIONS";
        set beresp.http.Access-Control-Allow-Headers = "AMP-Same-Origin, Content-Type, Accept, Origin, X-Requested-With";
    }

    # Set ban-lurker friendly custom headers.
    set beresp.http.X-Url = bereq.url;
    set beresp.http.X-Host = bereq.http.host;

    # Cache 404s, 301s with a short lifetime to protect the backend.
    if (beresp.status == 404 || beresp.status == 301) {
        set beresp.ttl = 10m;
    }

    # Cache 500s with a very short lifetime because we want to recover from fatal errors fast.
    if (beresp.status == 500) {
        set beresp.ttl = 12s;
    }

    # Remove the Set-Cookie header from static assets
    # This is just for cleanliness and is also done in vcl_deliver
    if (bereq.http.X-static-asset) {
        unset beresp.http.Set-Cookie;
    }

    # How much time items remain in cache after they become stale while Varnish fetches fresh content from the backend.
    set beresp.grace = 14d;
}