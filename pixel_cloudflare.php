<?php
/**
 * Copyright (C) 2025 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

// PrestaShop 1.7 does not load a module's `vendor/autoload.php`. Without this
// require the Pixel\Module\Cloudflare classes simply do not exist for PHP, and
// Symfony 3.4's ControllerResolver — which calls class_exists() before asking
// the container — kills the route with a 500.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Pixel_cloudflare extends Module
{
    /**
     * PrestaShop log severities.
     *
     * `PrestaShopLogger::LOG_SEVERITY_LEVEL_*` does not exist before PrestaShop 8
     * — on 1.7.7.4 the class declares no constants at all — so referencing them
     * throws "Undefined class constant" and returns a 500. One of the uses sits
     * inside a catch block, where it also swallowed the original error.
     */
    private const SEVERITY_INFO = 1;
    private const SEVERITY_ERROR = 3;

    /**
     * Module's constructor.
     */
    public function __construct()
    {
        $this->name = 'pixel_cloudflare';
        $this->version = '1.3.2';
        $this->author = 'Pixel Open';
        $this->tab = 'administration';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans(
            'Cloudflare',
            [],
            'Modules.Pixelcloudflare.Admin'
        );
        $this->description = $this->trans(
            'Cloudflare API features in Prestashop.',
            [],
            'Modules.Pixelcloudflare.Admin'
        );
        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];
    }

    /***************************/
    /** MODULE INITIALIZATION **/
    /***************************/

    /**
     * Install the module
     *
     * @return bool
     */
    public function install(): bool
    {
        return parent::install() &&
            $this->registerHook('displayDashboardToolbarTopMenu') &&
            $this->registerHook('actionClearCompileCache');
    }

    /**
     * Uninstall the module
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        return parent::uninstall() && $this->deleteConfigurations();
    }

    /**
     * Use the new translation system
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /***********/
    /** HOOKS **/
    /***********/

    /**
     * Clear Cloudflare cache
     *
     * @param mixed[] $params
     *
     * @return void
     * @throws Exception
     */
    public function hookActionClearCompileCache(array $params): void
    {
        try {
            $result = $this->get('pixel.cloudflare.api')->clearCache();
            foreach (($result['errors'] ?? []) as $error) {
                if (!isset($error['message'])) {
                    continue;
                }
                PrestaShopLogger::addLog(
                    (string)$error['message'],
                    self::SEVERITY_ERROR
                );
            }
            foreach (($result['messages'] ?? []) as $message) {
                if (!isset($message['message'])) {
                    continue;
                }
                PrestaShopLogger::addLog(
                    (string)$message['message'],
                    self::SEVERITY_INFO
                );
            }
            if ($result['success'] ?? false) {
                PrestaShopLogger::addLog(
                    $this->trans('Cloudflare cache has been flushed', [],'Modules.Pixelcloudflare.Admin'),
                    self::SEVERITY_INFO
                );
            }
        } catch (Throwable $throwable) {
            PrestaShopLogger::addLog(
                $this->trans('Unable to clear Cloudflare cache', [],'Modules.Pixelcloudflare.Admin'),
                self::SEVERITY_ERROR
            );
        }
    }

    /**
     * Add toolbar buttons
     *
     * @param mixed[] $params
     *
     * @return string
     * @throws Exception
     */
    public function hookDisplayDashboardToolbarTopMenu(array $params): string
    {
        $controller = $this->context->controller;
        $allowed = $controller->controller_type === 'admin' && $controller->php_self === 'AdminPerformance';

        if (!$allowed) {
            return '';
        }

        $buttons = [
            [
                'label' => $this->trans('Clear Cloudflare Cache', [], 'Modules.Pixelcloudflare.Admin'),
                'route' => 'admin_cloudflare_clear_cache',
                'class' => 'btn btn-info',
                'icon'  => 'delete'
            ]
        ];

        return $this->renderToolbar($buttons);
    }

    /*******************/
    /** CONFIGURATION **/
    /*******************/

    /**
     * Retrieve config fields
     *
     * @return array[]
     */
    protected function getConfigFields(): array
    {
        return [
            'CLOUDFLARE_ZONE_ID' => [
                'type'     => 'text',
                'label'    => $this->trans('Zone ID', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_ZONE_ID',
                'size'     => 20,
                'required' => true,
            ],
            'CLOUDFLARE_API_AUTHENTICATION_MODE' => [
                'type'     => 'select',
                'label'    => $this->trans('Authentication mode', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_API_AUTHENTICATION_MODE',
                'required' => true,
                'options' => [
                    'query' => [
                        [
                            'value' => 'api_token',
                            'name'  => $this->trans('API Token', [], 'Modules.Pixelcloudflare.Admin'),
                        ],
                        [
                            'value' => 'api_key',
                            'name'  => $this->trans('Global API Key', [], 'Modules.Pixelcloudflare.Admin'),
                        ],
                    ],
                    'id'   => 'value',
                    'name' => 'name',
                ],
            ],
            'CLOUDFLARE_API_TOKEN' => [
                'type'     => 'text',
                'label'    => $this->trans('API Token', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_API_TOKEN',
                'size'     => 20,
                'required' => false,
                'desc'     => $this->trans(
                    'A valid token from your Cloudflare Account with permission on "Cache Purge" for "Zone".',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                ),
            ],
            'CLOUDFLARE_API_KEY' => [
                'type'     => 'text',
                'label'    => $this->trans('Global API Key', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_API_KEY',
                'size'     => 20,
                'required' => false,
            ],
            'CLOUDFLARE_ACCOUNT_EMAIL' => [
                'type'     => 'text',
                'label'    => $this->trans('Account Email', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_ACCOUNT_EMAIL',
                'size'     => 20,
                'required' => false,
            ],
        ];
    }

    /**
     * This method handles the module's configuration page
     *
     * @return string
     */
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            foreach ($this->getConfigFields() as $code => $field) {
                $value = Tools::getValue($code);

                // Prestashop settings
                if ($field['required'] && empty($value)) {
                    return $this->displayError(
                            $this->trans('%field% is empty', ['%field%' => $field['label']], 'Modules.Pixelcloudflare.Admin')
                        ) . $this->displayForm();
                }
                if ($value && ($field['multiple'] ?? false) === true) {
                    $value = join(',', $value);
                }
                Configuration::updateValue($code, $value);
            }

            $output = $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Pixelcloudflare.Admin'));
        }

        return $output . $this->displayForm();
    }

    /**
     * Builds the configuration form
     *
     * @return string
     */
    public function displayForm(): string
    {
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Pixelcloudflare.Admin'),
                ],
                'input' => $this->getConfigFields(),
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Pixelcloudflare.Admin'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;
        $helper->submit_action = 'submit' . $this->name;

        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        foreach ($this->getConfigFields() as $code => $field) {
            // Prestashop settings
            $value = Tools::getValue($code, Configuration::get($code));

            if (!is_array($value) && ($field['multiple'] ?? false) === true) {
                $value = explode(',', (string)$value);
            }

            $helper->fields_value[$field['name']] = $value;
        }

        $form = $helper->generateForm([$form]);

        // `js.twig` holds plain JavaScript, without a single Twig tag, so it is
        // read straight from disk. Asking the container for `twig` breaks on
        // PrestaShop 1.7: a module configuration screen is served by the LEGACY
        // controller AdminModules, and the legacy container does not expose that
        // service, so `$this->get('twig')` throws ServiceNotFoundException and
        // the whole page returns a 500.
        $script = (string) @file_get_contents(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/config/js.twig'
        );

        return $form . $script;
    }

    /**
     * Delete configurations
     *
     * @return bool
     */
    protected function deleteConfigurations(): bool
    {
        foreach ($this->getConfigFields() as $key => $options) {
            Configuration::deleteByName($key);
        }

        return true;
    }
    /**
     * Render the toolbar buttons.
     *
     * Replaces `toolbar.html.twig`. Twig is not used for the same reason as in
     * displayForm() — the legacy container has no `twig` service on PrestaShop
     * 1.7 — and that template also relied on `path()`, which only exists inside
     * Symfony's Twig environment. The URL is resolved through the router; if the
     * router cannot be reached the button is skipped instead of breaking the
     * page it is displayed on.
     *
     * @param mixed[] $buttons
     *
     * @return string
     */
    private function renderToolbar(array $buttons): string
    {
        try {
            $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            $router = $container !== null ? $container->get('router') : null;
        } catch (\Throwable $e) {
            return '';
        }

        if ($router === null) {
            return '';
        }

        $html = '';
        foreach ($buttons as $button) {
            try {
                $url = $router->generate($button['route']);
            } catch (\Throwable $e) {
                continue;
            }

            $icon = !empty($button['icon'])
                ? '<i class="material-icons">' . htmlspecialchars((string) $button['icon'], ENT_QUOTES, 'UTF-8') . '</i>'
                : '';

            $html .= sprintf(
                '<a class="%s" href="%s">%s%s</a>',
                htmlspecialchars((string) ($button['class'] ?? ''), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'),
                $icon,
                htmlspecialchars((string) ($button['label'] ?? ''), ENT_QUOTES, 'UTF-8')
            );
        }

        return $html;
    }
}
