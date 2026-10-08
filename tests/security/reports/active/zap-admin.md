# ZAP Scanning Report

ZAP by [Checkmarx](https://checkmarx.com/).


## Summary of Alerts

| Risk Level | Number of Alerts |
| --- | --- |
| High | 0 |
| Medium | 0 |
| Low | 1 |
| Informational | 2 |




## Insights

| Level | Reason | Site | Description | Statistic |
| --- | --- | --- | --- | --- |
| Info | Informational | http://host.docker.internal:8080 | Percentage of responses with status code 2xx | 17 % |
| Info | Informational | http://host.docker.internal:8080 | Percentage of responses with status code 3xx | 35 % |
| Info | Informational | http://host.docker.internal:8080 | Percentage of responses with status code 4xx | 47 % |
| Info | Informational | http://host.docker.internal:8080 | Percentage of endpoints with content type application/json | 61 % |
| Info | Informational | http://host.docker.internal:8080 | Percentage of endpoints with content type text/html | 38 % |
| Info | Informational | http://host.docker.internal:8080 | Percentage of endpoints with method GET | 100 % |
| Info | Informational | http://host.docker.internal:8080 | Count of total endpoints | 67    |







## Alerts

| Name | Risk Level | Number of Instances |
| --- | --- | --- |
| Unexpected Content-Type was returned | Low | 27 |
| A Client Error response code was returned by the server | Informational | 45 |
| Non-Storable Content | Informational | Systemic |




## Alert Detail



### [ Unexpected Content-Type was returned ](https://www.zaproxy.org/docs/alerts/100001/)



##### Low (High)

### Description

A Content-Type of text/html was returned by the server.
This is not one of the types expected to be returned by an API.
Raised by the 'Alert on Unexpected Content Types' script

* URL: http://host.docker.internal:8080/7379982110081011494
  * Node Name: `http://host.docker.internal:8080/7379982110081011494`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/actuator/health
  * Node Name: `http://host.docker.internal:8080/actuator/health`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api
  * Node Name: `http://host.docker.internal:8080/api`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/
  * Node Name: `http://host.docker.internal:8080/api/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/7830842958112501438
  * Node Name: `http://host.docker.internal:8080/api/7830842958112501438`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin
  * Node Name: `http://host.docker.internal:8080/api/admin`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/
  * Node Name: `http://host.docker.internal:8080/api/admin/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/4930696163742887933
  * Node Name: `http://host.docker.internal:8080/api/admin/4930696163742887933`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/medications
  * Node Name: `http://host.docker.internal:8080/api/admin/medications`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/medications/
  * Node Name: `http://host.docker.internal:8080/api/admin/medications/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/medications/8801729410708461134
  * Node Name: `http://host.docker.internal:8080/api/admin/medications/8801729410708461134`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/schedules/7189091246207227436
  * Node Name: `http://host.docker.internal:8080/api/admin/schedules/7189091246207227436`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/schedules/calendar
  * Node Name: `http://host.docker.internal:8080/api/admin/schedules/calendar`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/schedules/calendar%3Fstart_date=http%253A%252F%252Fwww.google.com%252F&end_date=2026-09-30
  * Node Name: `http://host.docker.internal:8080/api/admin/schedules/calendar (end_date,start_date)`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family
  * Node Name: `http://host.docker.internal:8080/api/family`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/
  * Node Name: `http://host.docker.internal:8080/api/family/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/2176714235948364347
  * Node Name: `http://host.docker.internal:8080/api/family/2176714235948364347`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/incidents/7733178182102235721
  * Node Name: `http://host.docker.internal:8080/api/incidents/7733178182102235721`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional
  * Node Name: `http://host.docker.internal:8080/api/professional`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/
  * Node Name: `http://host.docker.internal:8080/api/professional/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/5641696959175121901
  * Node Name: `http://host.docker.internal:8080/api/professional/5641696959175121901`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/computeMetadata/v1/
  * Node Name: `http://host.docker.internal:8080/computeMetadata/v1/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/latest/meta-data/
  * Node Name: `http://host.docker.internal:8080/latest/meta-data/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/metadata/instance
  * Node Name: `http://host.docker.internal:8080/metadata/instance`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/opc/v1/instance/
  * Node Name: `http://host.docker.internal:8080/opc/v1/instance/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/opc/v2/instance/
  * Node Name: `http://host.docker.internal:8080/opc/v2/instance/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``
* URL: http://host.docker.internal:8080/openstack/latest/meta_data.json
  * Node Name: `http://host.docker.internal:8080/openstack/latest/meta_data.json`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `text/html`
  * Other Info: ``


Instances: 27

### Solution



### Reference




#### Source ID: 4

### [ A Client Error response code was returned by the server ](https://www.zaproxy.org/docs/alerts/100000/)



##### Informational (High)

### Description

A response code of 403 was returned by the server.
This may indicate that the application is failing to handle unexpected input correctly.
Raised by the 'Alert on HTTP Response Code Error' script

* URL: http://host.docker.internal:8080/7379982110081011494
  * Node Name: `http://host.docker.internal:8080/7379982110081011494`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/actuator/health
  * Node Name: `http://host.docker.internal:8080/actuator/health`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api
  * Node Name: `http://host.docker.internal:8080/api`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/
  * Node Name: `http://host.docker.internal:8080/api/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/7830842958112501438
  * Node Name: `http://host.docker.internal:8080/api/7830842958112501438`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin
  * Node Name: `http://host.docker.internal:8080/api/admin`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/
  * Node Name: `http://host.docker.internal:8080/api/admin/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/4930696163742887933
  * Node Name: `http://host.docker.internal:8080/api/admin/4930696163742887933`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/medications
  * Node Name: `http://host.docker.internal:8080/api/admin/medications`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/medications/
  * Node Name: `http://host.docker.internal:8080/api/admin/medications/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/medications/8801729410708461134
  * Node Name: `http://host.docker.internal:8080/api/admin/medications/8801729410708461134`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/schedules/7189091246207227436
  * Node Name: `http://host.docker.internal:8080/api/admin/schedules/7189091246207227436`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `405`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family
  * Node Name: `http://host.docker.internal:8080/api/family`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/
  * Node Name: `http://host.docker.internal:8080/api/family/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/2176714235948364347
  * Node Name: `http://host.docker.internal:8080/api/family/2176714235948364347`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/incidents
  * Node Name: `http://host.docker.internal:8080/api/family/incidents`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/incidents/
  * Node Name: `http://host.docker.internal:8080/api/family/incidents/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/older-adults
  * Node Name: `http://host.docker.internal:8080/api/family/older-adults`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/older-adults/
  * Node Name: `http://host.docker.internal:8080/api/family/older-adults/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/overview
  * Node Name: `http://host.docker.internal:8080/api/family/overview`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/overview/
  * Node Name: `http://host.docker.internal:8080/api/family/overview/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/routine
  * Node Name: `http://host.docker.internal:8080/api/family/routine`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/family/routine/
  * Node Name: `http://host.docker.internal:8080/api/family/routine/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/incidents/7733178182102235721
  * Node Name: `http://host.docker.internal:8080/api/incidents/7733178182102235721`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional
  * Node Name: `http://host.docker.internal:8080/api/professional`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/
  * Node Name: `http://host.docker.internal:8080/api/professional/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/5641696959175121901
  * Node Name: `http://host.docker.internal:8080/api/professional/5641696959175121901`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/older-adults
  * Node Name: `http://host.docker.internal:8080/api/professional/older-adults`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/older-adults/
  * Node Name: `http://host.docker.internal:8080/api/professional/older-adults/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/overview
  * Node Name: `http://host.docker.internal:8080/api/professional/overview`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/overview/
  * Node Name: `http://host.docker.internal:8080/api/professional/overview/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/reminders
  * Node Name: `http://host.docker.internal:8080/api/professional/reminders`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/reminders/
  * Node Name: `http://host.docker.internal:8080/api/professional/reminders/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/routine-notes
  * Node Name: `http://host.docker.internal:8080/api/professional/routine-notes`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/routine-notes%3Folder_adult_id=1
  * Node Name: `http://host.docker.internal:8080/api/professional/routine-notes (older_adult_id)`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/routine-notes/
  * Node Name: `http://host.docker.internal:8080/api/professional/routine-notes/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/routines
  * Node Name: `http://host.docker.internal:8080/api/professional/routines`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/routines/
  * Node Name: `http://host.docker.internal:8080/api/professional/routines/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/schedules
  * Node Name: `http://host.docker.internal:8080/api/professional/schedules`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/schedules/
  * Node Name: `http://host.docker.internal:8080/api/professional/schedules/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/vacation-requests
  * Node Name: `http://host.docker.internal:8080/api/professional/vacation-requests`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/vacation-requests/
  * Node Name: `http://host.docker.internal:8080/api/professional/vacation-requests/`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `403`
  * Other Info: ``
* URL: http://host.docker.internal:8080/metadata/instance
  * Node Name: `http://host.docker.internal:8080/metadata/instance`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/metadata/v1
  * Node Name: `http://host.docker.internal:8080/metadata/v1`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``
* URL: http://host.docker.internal:8080/openstack/latest/meta_data.json
  * Node Name: `http://host.docker.internal:8080/openstack/latest/meta_data.json`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `404`
  * Other Info: ``


Instances: 45

### Solution



### Reference



#### CWE Id: [ 388 ](https://cwe.mitre.org/data/definitions/388.html)


#### WASC Id: 20

#### Source ID: 4

### [ Non-Storable Content ](https://www.zaproxy.org/docs/alerts/10049/)



##### Informational (Medium)

### Description

The response contents are not storable by caching components such as proxy servers. If the response does not contain sensitive, personal or user-specific information, it may benefit from being stored and cached, to improve performance.

* URL: http://host.docker.internal:8080/api/admin/family-caregivers
  * Node Name: `http://host.docker.internal:8080/api/admin/family-caregivers`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/admin/medications/inventory
  * Node Name: `http://host.docker.internal:8080/api/admin/medications/inventory`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/ping
  * Node Name: `http://host.docker.internal:8080/api/ping`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/reminders
  * Node Name: `http://host.docker.internal:8080/api/professional/reminders`
  * Method: `GET`
  * Parameter: ``
  * Attack: ``
  * Evidence: `no-store`
  * Other Info: ``
* URL: http://host.docker.internal:8080/api/professional/schedules
  * Node Name: `http://host.docker.internal:8080/api/professional/schedules`
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


