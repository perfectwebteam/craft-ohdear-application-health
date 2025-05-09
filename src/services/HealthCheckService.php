<?php
/**
 * Oh Dear Application Health checker for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\ohdearapplicationhealth\services;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\models\Updates;
use DateTime;
use OhDear\HealthCheckResults\CheckResult;
use OhDear\HealthCheckResults\CheckResults;

class HealthCheckService extends Component
{
    private const SUPPORTED_PHP_VERSION = '8.1.0';

    public function runChecks(): CheckResults
    {
        $results = new CheckResults(new DateTime());

        $checks = [
            'addUpdateCheck',
            'addQueueCheck',
            'addPendingMigrationsCheck',
            'addProjectConfigCheck',
            'addErrorLogCheck',
            'addGitChangesCheck',
            'addSecurityHeadersCheck',
            'addPhpVersionCheck',
            'addAdminUsersCheck'
        ];

        foreach ($checks as $check) {
            $this->$check($results);
        }

        return $results;
    }

    private function addUpdateCheck(CheckResults $checkResults): void
    {
        $updateData = Craft::$app->getApi()->getUpdates([]);
        $updates = new Updates($updateData);

        $meta = [];
        $updateCount = 0;
        $updateNames = [];
        $criticalUpdates = [];
        $oldestUpdateDate = null;
        $now = new DateTime();

        if ($updates->cms->getHasReleases()) {
            $oldest = null;
            foreach ($updates->cms->releases as $release) {
                if ($oldest === null || $release->date < $oldest) {
                    $oldest = $release->date;
                }
            }

            $latest = $updates->cms->getLatest();
            $ageDays = $oldest ? $now->diff($oldest)->days : 'unknown';
            $meta['Craft CMS'] = sprintf('%s => %s (oldest update %s days ago)', Craft::$app->version, $latest->version, $ageDays);
            $updateNames[] = 'Craft CMS';
            $updateCount++;

            if ($updates->cms->getHasCritical()) {
                $criticalUpdates[] = 'Craft CMS';
            }

            if ($oldest && ($oldestUpdateDate === null || $oldest < $oldestUpdateDate)) {
                $oldestUpdateDate = $oldest;
            }
        }

        foreach ($updates->plugins as $pluginHandle => $pluginUpdate) {
            if ($pluginUpdate->getHasReleases()) {
                try {
                    $pluginInfo = Craft::$app->getPlugins()->getPluginInfo($pluginHandle);
                    if ($pluginInfo['isInstalled']) {
                        $oldest = null;
                        foreach ($pluginUpdate->releases as $release) {
                            if ($oldest === null || $release->date < $oldest) {
                                $oldest = $release->date;
                            }
                        }

                        $latest = $pluginUpdate->getLatest();
                        $ageDays = $oldest ? $now->diff($oldest)->days : 'unknown';
                        $meta[$pluginInfo['name']] = sprintf('%s => %s (oldest update %s days ago)', $pluginInfo['version'], $latest->version, $ageDays);
                        $updateNames[] = $pluginInfo['name'];
                        $updateCount++;

                        if ($pluginUpdate->getHasCritical()) {
                            $criticalUpdates[] = $pluginInfo['name'];
                        }

                        if ($oldest && ($oldestUpdateDate === null || $oldest < $oldestUpdateDate)) {
                            $oldestUpdateDate = $oldest;
                        }
                    }
                } catch (\craft\errors\InvalidPluginException $e) {
                    continue;
                }
            }
        }

        $status = CheckResult::STATUS_OK;
        $message = $updateCount === 0
            ? 'Plugins and CMS are up to date'
            : implode(', ', $updateNames) . ' have updates available';

        $shortSummary = "{$updateCount} updates";

        if (!empty($criticalUpdates)) {
            $status = CheckResult::STATUS_WARNING;
            $message = '🚨 ' . $message . ' (critical updates: ' . implode(', ', $criticalUpdates) . ')';
            $shortSummary = "🚨 {$updateCount} updates (critical)";
        } elseif ($oldestUpdateDate !== null) {
            $diff = $now->diff($oldestUpdateDate);
            if ($diff->days > 30) {
                $status = CheckResult::STATUS_WARNING;
                $message .= ' (oldest update is over 30 days old)';
            }
        }

        $checkResults->addCheckResult(new CheckResult(
            name: 'Updates',
            label: 'Available Updates',
            notificationMessage: $message,
            shortSummary: $shortSummary,
            status: $status,
            meta: $meta
        ));
    }

    private function addQueueCheck(CheckResults $checkResults): void
    {
        $queueInfo = Craft::$app->queue->getJobInfo();
        $meta = [
            'Total jobs' => $queueInfo['totalJobs'] ?? 0,
            'Failed jobs' => $queueInfo['failedJobs'] ?? 0,
        ];

        $status = ($meta['Failed jobs'] > 0) ? CheckResult::STATUS_FAILED : CheckResult::STATUS_OK;
        $message = $meta['Total jobs'] === 0 ? 'Queue is empty' : "{$meta['Total jobs']} jobs in the queue";

        $checkResults->addCheckResult(new CheckResult(
            name: 'Queue',
            label: 'Queue Status',
            notificationMessage: $message,
            shortSummary: "{$meta['Total jobs']} jobs, {$meta['Failed jobs']} failed",
            status: $status,
            meta: $meta
        ));
    }

    private function addPendingMigrationsCheck(CheckResults $checkResults): void
    {
        $pendingMigrations = Craft::$app->migrator->getNewMigrations();
        $count = count($pendingMigrations);

        $status = $count === 0 ? CheckResult::STATUS_OK : CheckResult::STATUS_FAILED;
        $message = $count === 0 ? 'No pending migrations.' : "{$count} pending migrations.";

        $checkResults->addCheckResult(new CheckResult(
            name: 'Migrations',
            label: 'Pending Migrations',
            notificationMessage: $message,
            shortSummary: $message,
            status: $status,
            meta: ['Pending migrations' => $count]
        ));
    }

    private function addErrorLogCheck(CheckResults $checkResults): void
    {
        $logPath = Craft::$app->getPath()->getLogPath();
        $dateSuffix = date('Y-m-d');
        $logFiles = [
            "web-{$dateSuffix}.log",
            "queue-{$dateSuffix}.log",
            "console-{$dateSuffix}.log",
        ];

        $meta = [];
        $errorCount = 0;

        foreach ($logFiles as $logFile) {
            $filePath = $logPath . DIRECTORY_SEPARATOR . $logFile;

            if (file_exists($filePath)) {
                try {
                    $logContents = file_get_contents($filePath);
                    $fileErrorCount = substr_count($logContents, '[error]');
                    $meta[$logFile] = "{$fileErrorCount} errors";
                    $errorCount += $fileErrorCount;
                } catch (\Exception $e) {
                    $meta[$logFile] = 'Error reading file';
                }
            } else {
                $meta[$logFile] = 'File not found';
            }
        }

        $status = $errorCount === 0 ? CheckResult::STATUS_OK : CheckResult::STATUS_WARNING;
        $message = $errorCount === 0 ? 'No recent errors in logs.' : "{$errorCount} total errors found in logs.";

        $checkResults->addCheckResult(new CheckResult(
            name: 'ErrorLogs',
            label: 'Error Log Monitoring',
            notificationMessage: $message,
            shortSummary: $message,
            status: $status,
            meta: $meta
        ));
    }

    private function addGitChangesCheck(CheckResults $checkResults): void
    {
        $repoPath = Craft::getAlias('@root');

        if (!is_dir($repoPath . '/.git')) {
            $checkResults->addCheckResult(new CheckResult(
                name: 'GitChanges',
                label: 'Git Repository Status',
                notificationMessage: 'No Git repository found at the server.',
                shortSummary: 'Git repository missing',
                status: CheckResult::STATUS_WARNING,
                meta: ['Git status' => 'Repository not found']
            ));
            return;
        }

        try {
            exec("cd {$repoPath} && git status --porcelain", $output, $statusCode);
            // Get current branch name
            exec("cd {$repoPath} && git rev-parse --abbrev-ref HEAD", $branchOutput, $branchStatusCode);

            if ($statusCode !== 0 || $branchStatusCode !== 0) {
                throw new \Exception('Git command failed');
            }

            $currentBranch = trim($branchOutput[0] ?? 'Unknown');
            $uncommittedChanges = count($output);

            $meta = [
                'Current branch' => $currentBranch,
                'Uncommitted changes' => $uncommittedChanges,
                'Changed files' => $uncommittedChanges > 0 ? implode(', ', $output) : 'None',
            ];

            $status = $uncommittedChanges === 0 ? CheckResult::STATUS_OK : CheckResult::STATUS_FAILED;
            $message = $uncommittedChanges === 0
                ? "No uncommitted changes on branch {$currentBranch}."
                : "{$uncommittedChanges} uncommitted changes detected on branch {$currentBranch}.";

            $checkResults->addCheckResult(new CheckResult(
                name: 'GitChanges',
                label: 'Git Repository Status',
                notificationMessage: $message,
                shortSummary: $message,
                status: $status,
                meta: $meta
            ));
        } catch (\Exception $e) {
            $checkResults->addCheckResult(new CheckResult(
                name: 'GitChanges',
                label: 'Git Repository Status',
                notificationMessage: 'Error checking Git repository status.',
                shortSummary: 'Git status check failed',
                status: CheckResult::STATUS_FAILED,
                meta: ['Error' => $e->getMessage()]
            ));
        }
    }

    private function addSecurityHeadersCheck(CheckResults $checkResults): void
    {
        $url = Craft::$app->getRequest()->getHostInfo();
        $requiredHeaders = [
            'Content-Security-Policy',
            'X-Frame-Options',
            'Strict-Transport-Security',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ];

        $meta = [];
        $missingHeaders = [];
        $status = CheckResult::STATUS_OK;

        try {
            $headers = get_headers($url, 1);

            foreach ($requiredHeaders as $header) {
                if (isset($headers[$header])) {
                    $meta[$header] = 'Present';
                } else {
                    $meta[$header] = 'Missing';
                    $missingHeaders[] = $header;
                    $status = CheckResult::STATUS_WARNING;
                }
            }
        } catch (\Exception $e) {
            $checkResults->addCheckResult(new CheckResult(
                name: 'SecurityHeaders',
                label: 'Security Headers',
                notificationMessage: 'Error fetching headers.',
                shortSummary: 'Failed to check security headers',
                status: CheckResult::STATUS_FAILED,
                meta: ['Error' => $e->getMessage()]
            ));
            return;
        }

        $message = empty($missingHeaders) ? 'All required security headers are present.' : 'Missing headers: ' . implode(', ', $missingHeaders);

        $checkResults->addCheckResult(new CheckResult(
            name: 'SecurityHeaders',
            label: 'Security Headers Check',
            notificationMessage: $message,
            shortSummary: $message,
            status: $status,
            meta: $meta
        ));
    }

    private function addProjectConfigCheck(CheckResults $checkResults): void
    {
        $isConfigApplied = Craft::$app->getProjectConfig()->areChangesPending();

        if ($isConfigApplied) {
            $status = CheckResult::STATUS_FAILED;
            $message = 'Unapplied project config changes detected.';
        } else {
            $status = CheckResult::STATUS_OK;
            $message = 'Project config is fully synchronized.';
        }

        $checkResults->addCheckResult(new CheckResult(
            name: 'ProjectConfig',
            label: 'Project Config Status',
            notificationMessage: $message,
            shortSummary: $message,
            status: $status
        ));
    }

    private function addPhpVersionCheck(CheckResults $checkResults): void
    {
        $currentVersion = PHP_VERSION;
        $supportedVersion = self::SUPPORTED_PHP_VERSION;
        $status = version_compare($currentVersion, $supportedVersion, '>=') ? CheckResult::STATUS_OK : CheckResult::STATUS_WARNING;

        $message = $status === CheckResult::STATUS_OK
            ? "PHP version {$currentVersion} is supported."
            : "PHP version {$currentVersion} is below supported minimum {$supportedVersion}.";

        $checkResults->addCheckResult(new CheckResult(
            name: 'PHP Version',
            label: 'PHP Version Check',
            notificationMessage: $message,
            shortSummary: $currentVersion,
            status: $status,
            meta: []
        ));
    }

    private function addAdminUsersCheck(CheckResults $checkResults): void
    {
        $adminUsers = User::find()->admin()->status(['not', 'suspended'])->orderBy(['lastLoginDate' => SORT_DESC])->all();
        $adminCount = count($adminUsers);
        $adminMeta = [];
        $inactiveAdmins = [];
        $status = CheckResult::STATUS_OK;
        $oneYearAgo = (new DateTime())->modify('-1 year');

        foreach ($adminUsers as $user) {
            $lastLogin = $user->lastLoginDate ? $user->lastLoginDate->format('Y-m-d H:i:s') : 'Never';
            $adminMeta[$user->username] = ['Last Login' => $lastLogin];

            if ($user->lastLoginDate === null || $user->lastLoginDate < $oneYearAgo) {
                $inactiveAdmins[] = $user->username;
            }
        }

        if (!empty($inactiveAdmins)) {
            $status = CheckResult::STATUS_WARNING;
            $message = 'Inactive admin accounts (>1 year): ' . implode(', ', $inactiveAdmins);
        } else {
            $message = 'All admin users active within the past year.';
        }

        $checkResults->addCheckResult(new CheckResult(
            name: 'AdminUsers',
            label: 'Admin Users Check',
            notificationMessage: $message,
            shortSummary: "$adminCount admin users",
            status: $status,
            meta: $adminMeta
        ));
    }
}
