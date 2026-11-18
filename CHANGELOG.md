# Changelog

All notable changes to IAWP Query Helper will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Three named queries, each as a REST route under `iawp-query-helper/v1` and an ability under `iawp-query-helper/`: `site-summary`, `top-posts` and `traffic-over-time`.
- Date presets matching the Independent Analytics dashboard, explicit `start`/`end` dates, and `compare` with `previous_year` or `previous_period`.
- Safety rails on every query: a 20 second statement timeout (MariaDB and MySQL), two queries at once per site, a 366 day maximum range, capped page sizes and paging depth.
- Filters to tighten the rails: `iawpqh_statement_timeout`, `iawpqh_concurrency_slots`, `iawpqh_max_range_days`.
- Admin notices when Independent Analytics is missing, or its database version is newer than this plugin has been tested with.
- GitHub updater, switched off with `iawpqh_updater_enabled`.
