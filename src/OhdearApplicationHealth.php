<?php
/**
 * Oh Dear Application Health checker for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\ohdearapplicationhealth;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use perfectwebteam\ohdearapplicationhealth\models\Settings;
use perfectwebteam\ohdearapplicationhealth\services\HealthCheckService;

class OhdearApplicationHealth extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;
    public bool $hasCpSection = false;

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'healthCheck' => HealthCheckService::class,
        ]);

        // Register the route for the health feed
        Craft::$app->getUrlManager()->addRules([
            'application-health.json' => 'ohdear-application-health/health/check',
        ], false);
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }
}
