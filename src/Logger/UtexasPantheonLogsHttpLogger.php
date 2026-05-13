<?php

namespace Drupal\utexas_pantheon_logs_http\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\UpdateException;
use GuzzleHttp\Exception\RequestException;

/**
 * Implements a Logs Http Logger instance.
 */
class UtexasPantheonLogsHttpLogger implements UtexasPantheonLogsHttpLoggerInterface {
  use RfcLoggerTrait;

  /**
   * A configuration object containing Logs http settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $logMessageParser;

  /**
   * The severity levels array.
   *
   * @var array
   */
  protected $severityLevels;

  /**
   * The cache of the events.
   *
   * @var array
   */
  protected $cache = [];

  /**
   * Clear the events by setting a new array to the variable.
   */
  public function reset() {
    $this->cache = [];
  }

  /**
   * Constructs a LogsHttpLogger object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser) {
    $this->config = $config_factory->get('utexas_pantheon_logs_http.settings');
    $this->logMessageParser = $parser;
    $this->severityLevels = RfcLogLevel::getLevels();
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    if (!$this->isEnabled()) {
      // Service is disabled.
      return;
    }

    if ($level > $this->config->get('severity_level')) {
      // Severity level is above the ones we want to log.
      return;
    }

    $event = $this->registerEvent($level, $message, $context);
    if (!$event) {
      // No event created.
      return;
    }

    $this->addEventToCache($event);
  }

  /**
   * {@inheritdoc}
   *
   * To prevent multiple registration of the same error, we check that identical
   * events are not captured twice, thus reducing the final HTTP requests
   * needed.
   */
  public function registerEvent($level, string $message, array $context = []) {
    // Populate the message placeholders and then replace them in the message.
    $message_placeholders = $this->logMessageParser->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);
    $event = [
      'event' => [
        'timestamp' => $context['timestamp'],
        'type' => $context['channel'],
        'ip' => $context['ip'],
        'request_uri' => $context['request_uri'],
        'referer' => $context['referer'],
        'uid' => $context['uid'],
        'link' => strip_tags($context['link']),
        'message' => $message,
        'severity' => $this->severityLevels[$level]->getUntranslatedString(),
      ],
    ];

    if ($environment_uuid = $this->config->get('environment_uuid')) {
      $event['event']['uuid'] = $environment_uuid;
    }

    return $event;
  }

  /**
   * Add an event to static cache.
   *
   * Prevent adding the same event, occurred in the same request, twice.
   *
   * @param array $event
   *   The event to register in the static cache.
   *
   * @return bool
   *   TRUE if added to cache, otherwise FALSE, indicating is was already added
   *   previously.
   */
  protected function addEventToCache(array $event) {
    // Remove empty values, to prevent errors in the indexing of the JSON.
    $event = $this->arrayRemoveEmpty($event);

    // Prevent identical events.
    $event_clone = $event;
    unset($event_clone['event']['timestamp']);
    $key = md5(serialize($event_clone));

    $is_unique = !empty($this->cache[$key]);

    $this->cache[$key] = $event;

    return $is_unique;
  }

  /**
   * Deep array filter; Remove empty values.
   *
   * @param array $haystack
   *   The variable to filter.
   *
   * @return array
   *   The filtered array.
   */
  protected function arrayRemoveEmpty(array $haystack) {
    foreach ($haystack as $key => $value) {
      if (is_array($value)) {
        $haystack[$key] = $this->arrayRemoveEmpty($haystack[$key]);
      }

      if (empty($haystack[$key])) {
        unset($haystack[$key]);
      }
    }

    return $haystack;
  }

  /**
   * A getter for the current events.
   *
   * @return array
   *   Returns the current events.
   */
  public function getEvents() {
    return $this->cache;
  }

  /**
   * Check weather we should use Logs http module or not.
   *
   * Determine by checking the 'enabled' configuration, plus the 'url' must not
   * be empty.
   *
   * @return bool
   *   Returns TRUE if currently we should POST the data, otherwise returns
   *   FALSE.
   */
  public function isEnabled() {
    return $this->config->get('enabled') && !empty($this->getUrl());
  }

  /**
   * A getter for the url of the endpoint we should send the data to.
   *
   * @return string
   *   Returns the endpoint URL to POST data to.
   */
  public function getUrl() {
    return $this->config->get('url');
  }

  /**
   * A getter for the HTTP headers for the logging request.
   *
   * @return mixed
   *   Returns the endpoint URL to POST data to.
   */
  public function getHttpHeaders() {
    if ($this->getSplunkToken() === '') {
      return '';
    }
    return [
      'Content-Type' => 'application/json',
      'Authorization' => 'Splunk ' . $this->getSplunkToken(),
    ];
  }

  /**
   * A getter for the secure integration constant name.
   *
   * @return string
   *   Returns the Pantheon stunnel constant name.
   */
  public function getPantheonStunnel() {
    return $this->config->get('constant_name');
  }

  /**
   * A getter for the Splunk token.
   *
   * @return string
   *   Returns the Splunk token.
   */
  private function getSplunkToken() {
    // Load Splunk token.
    if (function_exists('pantheon_get_secret')) {
      $token = pantheon_get_secret('splunk_http_logger_token') ?? NULL;
      if (!is_null($token)) {
        return $token;
      }
    }
    return '';
  }

  /**
   * Runs on shutdown to clean up and display developer information.
   *
   * @see \Drupal\utexas_pantheon_logs_http\EventSubscriber\LogsHttpEventSubscriber
   */
  public static function shutdown() {
    /** @var \Drupal\utexas_pantheon_logs_http\Logger\UtexasPantheonLogsHttpLogger $logs_http_logger */
    $logs_http_logger = \Drupal::service('utexas_pantheon_logs_http.utexas_pantheon_logs_http_logger');
    if (!$logs_http_logger->isEnabled()) {
      return;
    }
    if (!defined($logs_http_logger->getPantheonStunnel())) {
      // This is being executed on a non-Pantheon environment (e.g., locally).
      return;
    }
    // Return if no new events found.
    if (!$events = $logs_http_logger->getEvents()) {
      return;
    }
    // Return if no splunk token found.
    if ($logs_http_logger->getHttpHeaders() === '') {
      return;
    }

    $url = $logs_http_logger->getUrl();
    $host = parse_url($url, PHP_URL_HOST);
    $connect_to = [
      sprintf(
        "%s:443:127.0.0.1:%d",
        $host,
        constant($logs_http_logger->getPantheonStunnel())
      ),
    ];
    // Send events to logs.
    foreach ($events as $event) {
      $client = \Drupal::httpClient();

      try {
        // Send data to Logs.
        $client->post($url, [
          'json' => $event,
          'headers' => $logs_http_logger->getHttpHeaders(),
          'debug' => TRUE,
          'curl' => [
            CURLOPT_STDERR => fopen('php://stderr', 'w'),
            CURLOPT_CONNECT_TO => $connect_to,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => TRUE,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
          ],
        ]);
      }
      catch (RequestException $e) {
        throw new UpdateException('Could not log action into Splunk.');
      }
    }
  }

}
