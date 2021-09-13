[![Build Status](https://travis-ci.org/Gizra/logs_http.svg?branch=7.x-1.x)](https://travis-ci.org/Gizra/logs_http)

# UTexas Pantheon Logs HTTP

> Provides JSON event pushing to Logs via the tag/http endpoint.

The tag/http method that this module provides is very useful when the
Logs syslog agent is not an option such as when a web hosting limitation
restricts installing custom web server software. This module provides a
decoupled push via watchdog depending on severity levels.
