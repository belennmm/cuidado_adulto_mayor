# ZAP Scanning Report

ZAP by [Checkmarx](https://checkmarx.com/).


## Summary of Alerts

| Risk Level | Number of Alerts |
| --- | --- |
| High | 0 |
| Medium | 2 |
| Low | 1 |
| Informational | 2 |




## Insights

| Level | Reason | Site | Description | Statistic |
| --- | --- | --- | --- | --- |
| Info | Informational | http://host.docker.internal:3000 | Percentage of responses with status code 2xx | 100 % |
| Info | Informational | http://host.docker.internal:3000 | Percentage of endpoints with content type application/javascript | 53 % |
| Info | Informational | http://host.docker.internal:3000 | Percentage of endpoints with content type image/svg+xml | 7 % |
| Info | Informational | http://host.docker.internal:3000 | Percentage of endpoints with content type text/css | 15 % |
| Info | Informational | http://host.docker.internal:3000 | Percentage of endpoints with content type text/html | 23 % |
| Info | Informational | http://host.docker.internal:3000 | Percentage of endpoints with method GET | 100 % |
| Info | Informational | http://host.docker.internal:3000 | Count of total endpoints | 13    |







## Alerts

| Name | Risk Level | Number of Instances |
| --- | --- | --- |
| CSP: style-src unsafe-inline | Medium | 4 |
| Sub Resource Integrity Attribute Missing | Medium | Systemic |
| Cross-Origin-Embedder-Policy Header Missing or Invalid | Low | 4 |
| Modern Web Application | Informational | 4 |
| Non-Storable Content | Informational | Systemic |




## Alert Detail



### [ CSP: style-src unsafe-inline ](https://www.zaproxy.org/docs/alerts/10055/)



##### Medium (High)

### Description

Content Security Policy (CSP) is an added layer of security that helps to detect and mitigate certain types of attacks. Including (but not limited to) Cross Site Scripting (XSS), and data injection attacks. These attacks are used for everything from data theft to site defacement or distribution of malware. CSP provides a set of standard HTTP headers that allow website owners to declare approved sources of content that browsers should be allowed to load on that page — covered types are JavaScript, CSS, HTML frames, fonts, images and embeddable objects such as Java applets, ActiveX, audio and video files.

* URL: http://host.docker.internal:3000
  * Node Name: `http://host.docker.internal:3000`
  * Method: `GET`
  * Parameter: `Content-Security-Policy`
  * Attack: ``
  * Evidence: `default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; frame-src https://www.youtube.com; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' data: https://fonts.gstatic.com https://unpkg.com; connect-src 'self' http://localhost:8080 http://127.0.0.1:8080`
  * Other Info: `style-src includes unsafe-inline.`
* URL: http://host.docker.internal:3000/
  * Node Name: `http://host.docker.internal:3000/`
  * Method: `GET`
  * Parameter: `Content-Security-Policy`
  * Attack: ``
  * Evidence: `default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; frame-src https://www.youtube.com; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' data: https://fonts.gstatic.com https://unpkg.com; connect-src 'self' http://localhost:8080 http://127.0.0.1:8080`
  * Other Info: `style-src includes unsafe-inline.`
* URL: http://host.docker.internal:3000/robots.txt
  * Node Name: `http://host.docker.internal:3000/robots.txt`
  * Method: `GET`
  * Parameter: `Content-Security-Policy`
  * Attack: ``
  * Evidence: `default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; frame-src https://www.youtube.com; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' data: https://fonts.gstatic.com https://unpkg.com; connect-src 'self' http://localhost:8080 http://127.0.0.1:8080`
  * Other Info: `style-src includes unsafe-inline.`
* URL: http://host.docker.internal:3000/sitemap.xml
  * Node Name: `http://host.docker.internal:3000/sitemap.xml`
  * Method: `GET`
  * Parameter: `Content-Security-Policy`
  * Attack: ``
  * Evidence: `default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; frame-src https://www.youtube.com; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' data: https://fonts.gstatic.com https://unpkg.com; connect-src 'self' http://localhost:8080 http://127.0.0.1:8080`
  * Other Info: `style-src includes unsafe-inline.`


Instances: 4

### Solution

Ensure that your web server, application server, load balancer, etc. is properly configured to set the Content-Security-Policy header.

### Reference


* [ https://www.w3.org/TR/CSP/ ](https://www.w3.org/TR/CSP/)
* [ https://caniuse.com/#search=content+security+policy ](https://caniuse.com/#search=content+security+policy)
* [ https://content-security-policy.com/ ](https://content-security-policy.com/)
* [ https://github.com/HtmlUnit/htmlunit-csp ](https://github.com/HtmlUnit/htmlunit-csp)
* [ https://web.dev/articles/csp#resource-options ](https://web.dev/articles/csp#resource-options)


#### CWE Id: [ 693 ](https://cwe.mitre.org/data/definitions/693.html)


#### WASC Id: 15

#### Source ID: 3

### [ Sub Resource Integrity Attribute Missing ](https://www.zaproxy.org/docs/alerts/90003/)



##### Medium (High)

### Description

The integrity attribute is missing on a script or link tag served by an external server. The integrity tag prevents an attacker who have gained access to this server from injecting a malicious content.

* URL: http://host.docker.internal:3000
  * Node Name: `http://host.docker.internal:3000`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&display=swap" />`
  * Other Info: ``
* URL: http://host.docker.internal:3000/
  * Node Name: `http://host.docker.internal:3000/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&display=swap" />`
  * Other Info: ``
* URL: http://host.docker.internal:3000/
  * Node Name: `http://host.docker.internal:3000/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto Mono:wght@400;500;700&display=swap" />`
  * Other Info: ``
* URL: http://host.docker.internal:3000/robots.txt
  * Node Name: `http://host.docker.internal:3000/robots.txt`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&display=swap" />`
  * Other Info: ``
* URL: http://host.docker.internal:3000/sitemap.xml
  * Node Name: `http://host.docker.internal:3000/sitemap.xml`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&display=swap" />`
  * Other Info: ``

Instances: Systemic


### Solution

Provide a valid integrity attribute to the tag.

### Reference


* [ https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Subresource_Integrity ](https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Subresource_Integrity)


#### CWE Id: [ 345 ](https://cwe.mitre.org/data/definitions/345.html)


#### WASC Id: 15

#### Source ID: 3

### [ Cross-Origin-Embedder-Policy Header Missing or Invalid ](https://www.zaproxy.org/docs/alerts/90004/)



##### Low (Medium)

### Description

Cross-Origin-Embedder-Policy header is a response header that prevents a document from loading any cross-origin resources that don't explicitly grant the document permission (using CORP or CORS).

* URL: http://host.docker.internal:3000
  * Node Name: `http://host.docker.internal:3000`
  * Method: `GET`
  * Parameter: `Cross-Origin-Embedder-Policy`
  * Attack: ``
  * Evidence: ``
  * Other Info: ``
* URL: http://host.docker.internal:3000/
  * Node Name: `http://host.docker.internal:3000/`
  * Method: `GET`
  * Parameter: `Cross-Origin-Embedder-Policy`
  * Attack: ``
  * Evidence: ``
  * Other Info: ``
* URL: http://host.docker.internal:3000/robots.txt
  * Node Name: `http://host.docker.internal:3000/robots.txt`
  * Method: `GET`
  * Parameter: `Cross-Origin-Embedder-Policy`
  * Attack: ``
  * Evidence: ``
  * Other Info: ``
* URL: http://host.docker.internal:3000/sitemap.xml
  * Node Name: `http://host.docker.internal:3000/sitemap.xml`
  * Method: `GET`
  * Parameter: `Cross-Origin-Embedder-Policy`
  * Attack: ``
  * Evidence: ``
  * Other Info: ``


Instances: 4

### Solution

Ensure that the application/web server sets the Cross-Origin-Embedder-Policy header appropriately, and that it sets the Cross-Origin-Embedder-Policy header to 'require-corp' for documents.
If possible, ensure that the end user uses a standards-compliant and modern web browser that supports the Cross-Origin-Embedder-Policy header (https://caniuse.com/mdn-http_headers_cross-origin-embedder-policy).

### Reference


* [ https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cross-Origin-Embedder-Policy ](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cross-Origin-Embedder-Policy)


#### CWE Id: [ 693 ](https://cwe.mitre.org/data/definitions/693.html)


#### WASC Id: 14

#### Source ID: 3

### [ Modern Web Application ](https://www.zaproxy.org/docs/alerts/10109/)



##### Informational (Medium)

### Description

The application appears to be a modern web application. If you need to explore it automatically then the Client Spider may well be more effective than the standard one.

* URL: http://host.docker.internal:3000
  * Node Name: `http://host.docker.internal:3000`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<script src="./js/app-loading-interceptors.js"></script>`
  * Other Info: `No links have been found while there are scripts, which is an indication that this is a modern web application.`
* URL: http://host.docker.internal:3000/
  * Node Name: `http://host.docker.internal:3000/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<script src="./js/app-loading-interceptors.js"></script>`
  * Other Info: `No links have been found while there are scripts, which is an indication that this is a modern web application.`
* URL: http://host.docker.internal:3000/robots.txt
  * Node Name: `http://host.docker.internal:3000/robots.txt`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<script src="./js/app-loading-interceptors.js"></script>`
  * Other Info: `No links have been found while there are scripts, which is an indication that this is a modern web application.`
* URL: http://host.docker.internal:3000/sitemap.xml
  * Node Name: `http://host.docker.internal:3000/sitemap.xml`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `<script src="./js/app-loading-interceptors.js"></script>`
  * Other Info: `No links have been found while there are scripts, which is an indication that this is a modern web application.`


Instances: 4

### Solution

This is an informational alert and so no changes are required.

### Reference




#### Source ID: 3

### [ Non-Storable Content ](https://www.zaproxy.org/docs/alerts/10049/)



##### Informational (Medium)

### Description

The response contents are not storable by caching components such as proxy servers. If the response does not contain sensitive, personal or user-specific information, it may benefit from being stored and cached, to improve performance.

* URL: http://host.docker.internal:3000
  * Node Name: `http://host.docker.internal:3000`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``
* URL: http://host.docker.internal:3000/assets/logo-jardin-abuelitos.svg
  * Node Name: `http://host.docker.internal:3000/assets/logo-jardin-abuelitos.svg`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``
* URL: http://host.docker.internal:3000/css/global.css
  * Node Name: `http://host.docker.internal:3000/css/global.css`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``
* URL: http://host.docker.internal:3000/css/login.css
  * Node Name: `http://host.docker.internal:3000/css/login.css`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``

Instances: Systemic


### Solution

The content may be marked as storable by ensuring that the following conditions are satisfied:
The request method must be understood by the cache and defined as being cacheable ("GET", "HEAD", and "POST" are currently defined as cacheable)
The response status code must be understood by the cache (one of the 1XX, 2XX, 3XX, 4XX, or 5XX response classes are generally understood)
The "no-store" cache directive must not appear in the request or response header fields
For caching by "shared" caches such as "proxy" caches, the "private" response directive must not appear in the response
For caching by "shared" caches such as "proxy" caches, the "Authorization" header field must not appear in the request, unless the response explicitly allows it (using one of the "must-revalidate", "public", or "s-maxage" Cache-Control response directives)
In addition to the conditions above, at least one of the following conditions must also be satisfied by the response:
It must contain an "Expires" header field
It must contain a "max-age" response directive
For "shared" caches such as "proxy" caches, it must contain a "s-maxage" response directive
It must contain a "Cache Control Extension" that allows it to be cached
It must have a status code that is defined as cacheable by default (200, 203, 204, 206, 300, 301, 404, 405, 410, 414, 501).

### Reference


* [ https://datatracker.ietf.org/doc/html/rfc7234 ](https://datatracker.ietf.org/doc/html/rfc7234)
* [ https://datatracker.ietf.org/doc/html/rfc7231 ](https://datatracker.ietf.org/doc/html/rfc7231)
* [ https://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html ](https://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html)


#### CWE Id: [ 524 ](https://cwe.mitre.org/data/definitions/524.html)


#### WASC Id: 13

#### Source ID: 3


