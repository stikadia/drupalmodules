<?php

namespace Drupal\auto_logout\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the auto logout settings form.
 */
class AutoLogoutSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_logout_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'auto_logout.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('auto_logout.settings');

    $form['session_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Session Timeout (in seconds)'),
      '#default_value' => $config->get('session_timeout'),
      '#required' => TRUE,
      '#min' => 1,
      '#description' => $this->t('Set the duration of inactivity after which users will be automatically logged out. Enter a value greater than zero.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('session_timeout') <= 0) {
      $form_state->setErrorByName('session_timeout', $this->t('The session timeout must be greater than zero.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('auto_logout.settings')
      ->set('session_timeout', $form_state->getValue('session_timeout'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
