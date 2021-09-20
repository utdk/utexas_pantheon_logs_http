
# UTexas Pantheon Logs HTTP
> Provides JSON event pushing to Splunk Logs via the tag/http endpoint.


The tag/http method that this module provides is very useful when the
Logs syslog agent is not an option such as when a web hosting limitation
restricts installing custom web server software. This module provides a
decoupled push via watchdog depending on severity levels.

The configuration that is provided, consists of variables which make the connection between Pantheon logs and Splunk, the variables are:

- **Endpoint**: The Splunk URL which is "pinged" with the event Post request
- **Secure integration constant name**: A Pantheon specific constant which holds a port that is required to build the URL (along with the endpoint) to connect into Splunk
- **Watchdog Severity**: The watchdog severity level value, which is set to log anything between *Info* and *Error* (anything but debug messages)

There is one last variable needed, but no provided by default for the field: **Splunk HTTP Event Collector Token**.

## Pre-requirements
Make sure that the site in Pantheon has already been given access to the `PANTHEON_SOIP_UTEXAS_SPLUNK_HEC` constant, which ultimately provides the port to use to send the Post request from Pantheon to Splunk.

## How to use
After enabling the module, default config will be set at `admin/config/services/logs-http-client`. The only variable that will need to be set manually is the `Splunk HTTP Event Collector Token`, which can be found in [Stache](https://stache.utexas.edu/) as `Splunk HEC Token PantheonAppLogs`. Once this is set, any subsequent watchdog logging shall send logs into Splunk. To verify that the site is logging data correctly, access this [Splunk link](https://splunk.security.utexas.edu/en-US/app/ut_eis1/search?q=search%20index%3Dservice-webpublishing%20source%3Dhttp%3APantheonAppLogs%20request_uri%3D%22https%3A%2F%2Flogs-http-utexas-its2.pantheonsite.io%2F*%22&display.page.search.mode=verbose&dispatch.sample_ratio=1&earliest=0&latest=&sid=1632159770.689754_8220FB8F-01FA-4F7E-929B-F56DE7E31D3B) and replace the `request_uri` parameter with `https://yourpantheonsite.pantheonsite.io/*`. If you get a hit while searching results, the module has beeen configured correctly.
