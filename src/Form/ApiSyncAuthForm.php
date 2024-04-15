<?php

declare(strict_types=1);

namespace Drupal\apisync\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Entity form for apisync_auth.
 */
class ApiSyncAuthForm extends EntityForm {

  /**
   * The config entity.
   *
   * Not directly typed to match parent class.
   *
   * @var \Drupal\apisync\Entity\ApiSyncAuthConfigInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $formState): array {
    $auth = $this->entity;
    if (empty($auth->getPluginsAsOptions())) {
      $this->messenger()->addError('No auth provider plugins found. Please enable an auth provider module, e.g. apisync_basicauth, before adding an auth config.');
      $form['#access'] = FALSE;
      return $form;
    }
    $formState->setBuildInfo($formState->getBuildInfo()
      + ['auth_config' => $this->config($auth->getConfigDependencyName())]);
    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#description' => $this->t('User-facing label for this project, e.g. "OAuth Full Sandbox"'),
      '#default_value' => $auth->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $auth->id(),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#required' => TRUE,
    ];

    // This is the element that contains all of the dynamic parts of the form.
    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => TRUE,
    ];

    $form['settings']['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Auth provider'),
      '#options' => $auth->getPluginsAsOptions(),
      '#required' => TRUE,
      '#default_value' => $auth->getPluginId(),
      '#ajax' => [
        'callback' => [$this, 'ajaxUpdateSettings'],
        'event' => 'change',
        'wrapper' => 'auth-settings',
      ],
    ];
    $default = [
      '#type' => 'container',
      '#title' => $this->t('Auth provider settings'),
      '#title_display' => FALSE,
      '#tree' => TRUE,
      '#prefix' => '<div id="auth-settings">',
      '#suffix' => '</div>',
    ];
    $form['settings']['provider_settings'] = $default;
    if ($auth->getPlugin() && !$formState->isRebuilding()) {
      $form['settings']['provider_settings'] += $auth->getPlugin()
        ->buildConfigurationForm([], $formState);
    }
    elseif ($formState->getValue('provider')) {
      $plugin = $this->entity->authManager()->createInstance($formState->getValue('provider'));
      $form['settings']['provider_settings'] += $plugin->buildConfigurationForm([], $formState);
    }
    elseif ($formState->getUserInput()) {
      $input = $formState->getUserInput();
      if (!empty($input['provider'])) {
        $plugin = $this->entity->authManager()
          ->createInstance($input['provider']);
        $form['settings']['provider_settings'] += $plugin->buildConfigurationForm([], $formState);
      }
    }
    $form['save_default'] = [
      '#type' => 'checkbox',
      '#title' => 'Save and set default',
      '#default_value' => $this->entity->isNew() || ($this->entity->authManager()->getProvider() && $this->entity->authManager()->getProvider()->id() == $this->entity->id()),
    ];
    return parent::form($form, $formState);
  }

  /**
   * AJAX callback to update the dynamic settings on the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return array
   *   The element to update in the form.
   */
  public function ajaxUpdateSettings(array &$form, FormStateInterface $formState): array {
    return $form['settings']['provider_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    parent::validateForm($form, $formState);

    if (!$formState->isSubmitted()) {
      return;
    }

    if (!empty($formState->getErrors())) {
      // Don't bother processing plugin validation if we already have errors.
      return;
    }

    $this->entity->getPlugin()->validateConfigurationForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);
    $this->entity->getPlugin()->submitConfigurationform($form, $formState);
    // If redirect is not already set, and we have no errors, send user back to
    // the AuthConfig listing page.
    if (!$formState->getErrors() && !$formState->getResponse() && !$formState->getRedirect()) {
      $formState->setRedirectUrl($this->entity->toUrl('collection'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState): void {
    parent::save($form, $formState);
    $this->entity->getPlugin()->save($form, $formState);
    if ($formState->getValue('save_default')) {
      $this
        ->configFactory()
        ->getEditable('apisync.settings')
        ->set('apisync_auth_provider', $this->entity->id())
        ->save();
    }
  }

  /**
   * Determines if the config already exists.
   *
   * @param string $id
   *   The config ID.
   *
   * @return bool
   *   TRUE if the config exists, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exists(string $id): bool {
    $action = $this->entityTypeManager->getStorage($this->entity->getEntityTypeId())->load($id);
    return !empty($action);
  }

}
