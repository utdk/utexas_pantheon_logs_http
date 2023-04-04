<?php

namespace Drupal\utexas_pantheon_logs_http\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Site\Settings;

/**
 * Defines a form that configures Logs http settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'utexas_pantheon_logs_http_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['utexas_pantheon_logs_http.settings'];
  }

  /**
   * Holds the name of the keys we holds in the variable.
   */
  public function defaultKeys() {
    return [
      'enabled',
      'url',
      'constant_name',
      'severity_level',
      'environment_uuid',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('utexas_pantheon_logs_http.settings');

    // Load Splunk token.
    $splunk_hec_token = '';
    if ($splunk_settings = file_get_contents(Settings::get('file_private_path') . '/splunk/splunk_settings.json')) {
      $settings = Json::decode($splunk_settings, TRUE);
      $splunk_hec_token = $settings['splunk_settings']['splunk_hec_token'];
    }
    $status = $splunk_hec_token ? 'Found' : 'Not found';
    $form['splunk_hec_token'] = [
      '#type'   => 'markup',
      '#markup' => $this->t('<h3><strong>Splunk Auth token: </strong> :status</h3>', [':status' => $status]),
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('Enable UTexas Pantheon Logs HTTP'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('The URL to POST the data to.'),
      '#default_value' => $config->get('url'),
    ];

    $form['constant_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secure integration constant name'),
      '#description' => $this->t('The PHP constant for the Pantheon stunnel.'),
      '#default_value' => $config->get('constant_name'),
    ];

    $form['severity_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Watchdog Severity'),
      '#options' => RfcLogLevel::getLevels(),
      '#default_value' => $config->get('severity_level'),
      '#description' => $this->t('The minimum severity level to be reached before an event is sent to Logs.'),
    ];

    $form['environment_uuid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unique ID'),
      '#description' => $this->t('An arbitrary ID that will identify the environment.'),
      '#default_value' => $config->get('environment_uuid'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('utexas_pantheon_logs_http.settings');

    foreach ($this->defaultKeys() as $key) {
      $config->set($key, $form_state->getValue($key));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
