# IAWP Query Helper

[![Release](https://img.shields.io/github/v/release/headwalluk/iawp-query-helper)](https://github.com/headwalluk/iawp-query-helper/releases/latest)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.9+-21759B.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)](LICENSE)

A WordPress plugin that lets an AI agent ask questions of [Independent Analytics](https://independentwp.com/) and get the same numbers the dashboard shows. "Which posts did best last month?", "How does this year compare with last?" and "Which referrers bring in sales?" each become one call to a read-only REST route, authenticated with an Application Password.

It's for site owners and agencies who already use Independent Analytics and want an agent to answer analytics questions for them or their clients, and for anyone building such an agent. The queries are a fixed, named set with documented parameters, so an agent can't run arbitrary SQL. Every query runs inside limits that keep a busy agent from slowing the site down for visitors. WooCommerce and form queries appear only on sites with Independent Analytics Pro.

This is an independent plugin. It isn't made or supported by the Independent Analytics team.

## Documentation

For site owners:

- [Getting started](docs/getting-started.md): requirements, installation, the agent's account, a first call, and what an agent can ask
- [How it works](docs/how-it-works.md): how each metric is calculated, what agents receive, the safety rails and who can run queries

For anyone connecting an agent:

- [Connecting an agent](docs/connecting-an-agent.md): authentication, every query's parameters, the response format and error codes

For developers:

- [Hooks and filters](docs/developers/hooks-and-filters.md): the supported extension points
- [Changelog](CHANGELOG.md)

## Licence

GPL v2 or later. See [LICENSE](LICENSE).
