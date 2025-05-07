<?php
/**
 * Oh Dear Application Health checker for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\ohdearapplicationhealth\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use perfectwebteam\ohdearapplicationhealth\OhdearApplicationHealth;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class HealthController extends Controller
{
    protected array|int|bool $allowAnonymous = true;
    private const CACHE_KEY = 'ohdear-health-check-json';
    private const CACHE_DURATION = 300;

    public function actionCheck(): Response
    {
        $this->validateHealthCheckSecret();

        $force = Craft::$app->request->getQueryParam('force');

        if (!$force && ($cachedResult = Craft::$app->cache->get(self::CACHE_KEY))) {
            return $this->asJson(json_decode($cachedResult, true));
        }

        $checkResults = OhdearApplicationHealth::getInstance()->healthCheck->runChecks();

        $resultJson = $checkResults->toJson();
        Craft::$app->cache->set(self::CACHE_KEY, $resultJson, self::CACHE_DURATION);

        return $this->asJson(json_decode($resultJson, true));
    }

    private function validateHealthCheckSecret(): void
    {
        $expectedSecret = App::env('OH_DEAR_HEALTH_REPORT_SECRET');
        $receivedSecret = Craft::$app->request->getHeaders()->get('oh-dear-health-check-secret');

        if (!$expectedSecret || $receivedSecret !== $expectedSecret) {
            throw new ForbiddenHttpException('Invalid or missing health check secret.');
        }
    }
}
