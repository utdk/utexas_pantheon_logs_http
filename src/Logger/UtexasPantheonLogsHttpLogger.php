<?php

namespace Drupal\utexas_pantheon_logs_http\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Site\Settings;

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
    if ($splunk_settings = file_get_contents(Settings::get('file_private_path') . '/splunk/splunk_settings.json')) {
      $settings = Json::decode($splunk_settings, TRUE);
      if (!empty($settings['splunk_settings']['splunk_hec_token'])) {
        return $settings['splunk_settings']['splunk_hec_token'];
      }
    }
    return '';
  }

}
