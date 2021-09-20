
# UTexas Pantheon Logs HTTP
> Provides JSON event pushing to Splunk Logs via the tag/http endpoint.

This module is based on the [Logs HTTP](https://www.drupal.org/project/logs_http) contrib module, which wass designed as a generalized solution for pushing Watchdog logs to an HTTP endpoint such as [Logstash](http://logstash.net/), or paid services such as [Loggly](loggly.com).

This custom fork adds needed functionality to allow Watchdog logs on a Pantheon-hosted site to be pushed to the [UT Austin Splunk instance](https://splunk.security.utexas.edu) via a [Splunk HTTP Event Collector (HEC)](https://dev.splunk.com/enterprise/docs/devtools/httpeventcollector/).

The HTTP connection code has been modified to meet the requirements of Pantheon's [Secure Integration](https://pantheon.io/docs/secure-integration) platform feature, which allows the messages to be sent over an existing secure tunnel between Pantheon and the UT Austin network.

This module also allows the use of a secure token which is required for connection to the Splunk HEC. See [(Splunk) HTTP Event Collector (HEC)](https://wikis.utexas.edu/pages/viewpage.action?pageId=196975636) for more information about HEC configuration.

The configuration that is provided, consists of variables which make the connection between Pantheon logs and Splunk, the variables are:

- **Endpoint**: The Splunk URL which is "pinged" with the event Post request
- **Secure integration constant name**: A Pantheon specific constant which holds a port that is required to build the URL (along with the endpoint) to connect into Splunk
- **Watchdog Severity**: The watchdog severity level value, which is set to log anything between *Info* and *Error* (anything but debug messages)

There is one last variable needed, but no provided by default for the field: **Splunk HTTP Event Collector Token**.


## Requirements
* Drupal 9
* Existing Pantheon Secure Integration configuration, configured with the IP address and port number of the UT Austin Splunk instance
* Existing Splunk HTTP Event Collector, configured with allow-list of Pantheon IP addresses provided by the Secure Integration configuration.

## How to use
After enabling the module, default config will be set at `admin/config/services/logs-http-client`. The only variable that will need to be set manually is the `Splunk HTTP Event Collector Token`, which can be found in [Stache](https://stache.utexas.edu/) as `Splunk HEC Token PantheonAppLogs`. Once this is set, any subsequent watchdog logging shall send logs into Splunk. To verify that the site is logging data correctly, access this [Splunk link](https://splunk.security.utexas.edu/en-US/app/ut_eis1/search?q=search%20index%3Dservice-webpublishing%20source%3Dhttp%3APantheonAppLogs%20request_uri%3D%22https%3A%2F%2Flogs-http-utexas-its2.pantheonsite.io%2F*%22&display.page.search.mode=verbose&dispatch.sample_ratio=1&earliest=0&latest=&sid=1632159770.689754_8220FB8F-01FA-4F7E-929B-F56DE7E31D3B) and replace the `request_uri` parameter with `https://yourpantheonsite.pantheonsite.io/*`. If you get a hit while searching results, the module has beeen configured correctly.

## Debugging
Using the Logger class to print notices on your watchdog logs is the best way to debug this module's content.
