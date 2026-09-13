<?php

namespace Beaver\Plugins\Sms;

use Beaver\Plugin\PluginBase;
use Beaver\Plugins\Sms\Repositories\CsmsRepository;
use Beaver\Plugins\Sms\Services\SmsService;

/**
 * SMS plugin for Beaver Framework.
 *
 * Responsibilities:
 *  - register the sms:: view namespace (resources/views)
 *  - register the sms:: translation namespace (lang/)
 *  - load the plugin routes (routes/web.php)
 *  - bind plugin services into the application container
 */
class SmsPlugin extends PluginBase
{
    public function boot(): void
    {
        // 1. Namespaces (views + translations)
        $this->loadViews();          // → view('sms::...')
        $this->loadTranslations();   // → __('sms::...')

        // 2. Routes
        $this->loadRoutes();         // → routes/web.php

        // 3. Services
        $this->registerServices();

        error_log("[SMS] carregado v{$this->version()} de {$this->path}");
    }

    /**
     * Bind plugin services into the application container,
     * so controllers can resolve them with $app->make(...).
     */
    protected function registerServices(): void
    {
        $this->app()->instance(SmsService::class, new SmsService(
            endpoint:   $this->resolve('sms_provider.url', 'SMS_PROVIDER_URL', ''),
            account:    $this->resolve('sms_provider.account', 'SMS_PROVIDER_ACCOUNT', ''),
            licensekey: $this->resolve('sms_provider.key', 'SMS_PROVIDER_KEY', ''),
            alfaSender: $this->resolve('sms_provider.sender', 'SMS_PROVIDER_SENDER', 'Onidesk'),
            envio24:    (int) $this->resolve('sms_provider.envio24', 'SMS_PROVIDER_ENVIO24', 1),
            ttl:        (int) $this->resolve('sms_provider.ttl', 'SMS_PROVIDER_TTL', 24),
        ));

        $this->app()->instance(CsmsRepository::class, new CsmsRepository());
    }

    /**
     * Resolve a setting, in this order:
     *   1. plugin config/settings.php (via $this->config())
     *   2. environment variable (via env())
     *   3. hardcoded default
     */
    private function resolve(string $configKey, string $envKey, mixed $default = null): mixed
    {
        $value = $this->config($configKey);

        if ($value !== null && $value !== '') {
            return $value;
        }

        return env($envKey, $default);
    }
}
