# Oh Dear Application Health Changelog

Oh Dear Application Health checker for Craft CMS.

## 1.1.2 – 2025-06-06
### Fixed
- Add settings model to prevent Craft warning as we can optionally use a config file

## 1.1.1 – 2025-06-06
### Fixed
- Set plugin settings flag

## 1.1.0 – 2025-05-09
### Added
- Support for `config/ohdear-application-health.php` config file (example in documentation)
- Configurable checks (enable/disable individual checks)
- Configurable thresholds:
    - `oldestUpdateWarningDays`
    - `minimumPhpVersion`
    - `inactiveAdminThreshold`
    - `requiredSecurityHeaders`
    - `gitRepoPath`
- Added support for overriding required security headers
- Added support for overriding Git repository path
- Queue status check improved, total and warning thresholds

## 1.0.0 – 2025-05-06
### Added
- Initial release with:
    - Update check
    - Queue status check
    - Pending migrations check
    - Error log check
    - Git repository status check
    - Security headers check
    - Project config check
    - PHP version check
    - Admin users check