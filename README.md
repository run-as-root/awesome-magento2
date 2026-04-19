# Awesome Magento 2 [![Awesome](https://cdn.rawgit.com/sindresorhus/awesome/d7305f38d29fed78fa85652e3a63e154dd8e8829/media/badge.svg)](https://github.com/sindresorhus/awesome)[![Project Status: Active – The project has reached a stable, usable state and is being actively developed.](https://www.repostatus.org/badges/latest/active.svg)](https://www.repostatus.org/#active)

<div align="center">
	<a href="https://vshymanskyy.github.io/StandWithUkraine">
		<img width="500" height="350" src="media/logo-ua.svg" alt="Awesome">
		<img src="https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner2-direct.svg">
	</a>
	<br>
	<br>
	<br>
	<br>
	<hr>
</div>


> A curated list of awesome Magento 2 Extensions & Resources.

- [What is an awesome list?](https://github.com/sindresorhus/awesome/blob/master/awesome.md)
- [Contribution guide](contributing.md) [![contributions welcome](https://img.shields.io/badge/contributions-welcome-brightgreen.svg?style=flat)](https://github.com/DavidLambauer/awesome-magento2/issues)

---

## Table of Contents

- [What is Magento?](#magento)
- [Events](#events)
- [Frontends](#frontends)
- [Tools](#tools)
- [Open Source Extensions](#open-source-extensions)
- [Blogs](#blogs)
- [Education](#learning)
- [Platforms](#platforms)
- [Official Resources](#official-resources)

---

## What is Magento?

Magento is an open-source e-commerce application that allows you to create webshops. We often speak of a frontend (the
storefront where customers buy products) and a backend (the Magento Admin Panel where customers and products are being
managed). The open source bit refers to the fact that the source code of Magento (PHP, HTML, CSS, JS, XML, and others)
is distributed under an open-source license (OSLv3) that allows anyone to reuse the code and make changes to it. This
open-source aspect has led to the massive popularity of the product Magento so that we often use the word Magento to
refer to either the product, the community around it or both.

Magento was started by a company called Varien, and with Magento version 1 (first released in 2008), popularity began to grow.
Magento version 2 was first released in November 2015 but faced a problematic adoption because of its complex
architecture and outdated features (KnockoutJS, RequireJS, Zend Framework 1). On the storefront part,
this led to various new frontends. In 2018, Magento was acquired by Adobe. Later, Magento Enterprise was integrated
into the Adobe cloud as Adobe Commerce Cloud, while the Magento Community Edition was relabeled Magento Open Source. In
the community, there was uncertainty whether Adobe would maintain Magento Open Source in the long run in the way
the community would see fit. This uncertainty led to a community initiative called Mage-OS.

Also see:

- [en.wikipedia.org/wiki/Magento](https://en.wikipedia.org/wiki/Magento)

## Events: Meet the community

- [MageUnconference 🇩🇪](https://www.mageunconference.org/) - A Magento Unconference in Germany.
- [MageUnconference 🇳🇱](https://mageunconference.nl/) - A Magento Unconference in the Netherlands.
- [Meet Commerce](https://www.meetcommerce.com/) - A global series of conferences focused on commerce and innovation.

### Meet Magento

[Meet Magento events](https://www.meet-magento.com/) bring together everyone from merchants through developers, solution and technology providers, and 
marketers—and we continue to expand.

- [Meet Magento Baltics](https://meetmagentobaltics.com/)
- [Meet Magento Brazil](https://meetmagentobrasil.org/)
- [Meet Magento Florida](https://meetmagentofl.com/)
- [Meet Magento India](https://www.meetmagento.in/)
- [Meet Magento Malaysia](https://www.meetmagento.asia/)
- [Meet Magento Netherlands](https://nl.meet-magento.com/)
- [Meet Magento New York City](https://meetmagentonyc.com/)
- [Meet Magento Poland](https://meetmagento.pl/)
- [Meet Magento Romania](https://ro.meet-magento.com/)
- [Meet Magento Singapore](https://meetmagento.sg/)
- [Meet Magento UK](https://meet-magento.co.uk/)

## Front-ends

The storefront of Magento 2 can be styled in numerous ways:

- [Magento Luma](https://developer.adobe.com/commerce/frontend-core/guide/) - Magento 2's default demo theme (extends Magento/blank). The name also refers to the whole Luma stack: XML layout + blocks/containers + PHTML templates, enriched with LESS-compiled CSS and RequireJS/KnockoutJS/jQuery.
- [Adobe PWA Studio](https://developer.adobe.com/commerce/pwa-studio/) - Adobe's headless React frontend. GraphQL client; offers Venia theme, Peregrine hooks, Buildpack (Webpack) and UPWARD (SSR/image middleware).
- [Hyvä](https://hyva.io/) - Luma replacement using TailwindCSS and AlpineJS. Commercial license. Active compatibility-module ecosystem.
- [Alokai](https://github.com/vuestorefront/vue-storefront) 🫡 - Formerly Vue Storefront — headless frontend framework.
- [ScandiPWA](https://github.com/scandipwa/scandipwa) - React/Redux PWA theme for Magento 2.3+.
- [Breeze Evolution](https://breezefront.com/themes) - Lightweight Luma-compatible theme targeting 100 PageSpeed.
- [Front-Commerce](https://www.front-commerce.com/) - French PWA front-end solution for Magento.

## Tools

- [n98-magerun2](https://github.com/netz98/n98-magerun2) 🫡 - The CLI Swiss Army Knife for Magento 2.
- [RabbitMQ Retry Mechanism](https://github.com/run-as-root/magento2-message-queue-retry) - Magento 2 extension that brings possibility to retry RabbitMQ failed messages.
- [MageForge](https://github.com/OpenForgeProject/mageforge) 🫡 - Magento 2 CLI automatic theme builder (Hyvä ready).
- [Tablerates Generator](https://www.tableratesgenerator.com/) - Generate tablerates online.
- [Mage2Gen](https://mage2gen.com/) - Online module creator.
- [Mage Chrome Toolbar](https://github.com/magespecialist/mage-chrome-toolbar) - Chrome extension for Magento 2 development by MageSpecialist.
- [MageSpecialist DevTools for Magento 2](https://github.com/magespecialist/m2-MSP_DevTools) - Developer toolbar for Magento 2.
- [magento2docker](https://github.com/aliuosio/magento2docker) - MariaDB, PHP, Redis, ElasticSearch in one Dockerfile for fast demo/development environments.
- [markshust/docker-magento](https://github.com/markshust/docker-magento) - Mark Shust's Docker configuration for Magento.
- [Warden](https://github.com/wardenenv/warden) 🫡 - CLI utility for working with docker-compose environments by David Alger.
- [DDEV](https://github.com/ddev/ddev) 🔥 🫡 - Open source tool for launching local web development environments in minutes. Supports PHP, Node.js and Python.
- [AmpersandHQ/ampersand-magento2-upgrade-patch-helper](https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper) - Helper script to aid upgrading Magento 2 websites by detecting overrides.
- [PhpStorm Magento2 Extension](https://github.com/magento/magento2-phpstorm-plugin) 🫡 - Official PhpStorm Magento 2 extension.
- [PhpInsights](https://github.com/nunomaduro/phpinsights) 🔥 🫡 - PHP quality checks with Magento 2 presets.
- [Tango](https://github.com/roma-glushko/tango) 🫡 - CLI for analyzing access logs.
- [Magento 2 Composer patches helper](https://chrome.google.com/webstore/detail/magento-2-composer-patche/gfndadbceejgfjahpfaijcacnmdloiad) - Chrome extension to create copy-pastable composer patch definitions for vaimo/composer-patches.
- [Migrate DB Magento 2 Commerce to Magento 2 Open-Source](https://github.com/opengento/magento2-downgrade-ee-ce) - Migrate a Magento 2 Commerce database to Magento 2 Open Source.
- [Magento 2 Database Synchronizer](https://github.com/jellesiderius/mage-db-sync) 🫡 - Database synchronizer for Magento 2 (and WordPress), based on Magerun2. Keeps development, staging and production in sync.
- [Magento 2 Url Data Integrity Checker](https://github.com/baldwin-agency/magento2-module-url-data-integrity-checker) 🫡 - Magento 2 module that finds potential URL-related problems in your catalog data.
- [Mage Wizard](https://github.com/clickAndMortar/mage-wizard) - Local web UI to view and create modules, plugins, configs, observers, commands, crontabs directly in a Magento 2 codebase.
- [Mage](https://github.com/GrimLink/mage) 🫡 - Simplifies bin/magento commands with shortcuts and productivity helpers.
- [Magento Log Viewer (VS Code extension)](https://marketplace.visualstudio.com/items?itemName=MathiasElle.magento-log-viewer) - VS Code extension to view, watch and manage Magento log files and reports directly in your workspace.

<details>
<summary>🪦 Graveyard — projects no longer recommended</summary>

- [Documentation Search for Alfred](https://github.com/DavidLambauer/Alfred-Workflow-Magento-2-DevDocs-Search) - Alfred workflow integrating the official Magento 2 documentation search.
- [Pestle](https://github.com/astorm/pestle) - Code generation tool by Alan Storm.
- [Masquerade](https://github.com/elgentos/masquerade) - Faker-driven, configuration-based, platform-agnostic, locale-compatible data faker tool.
- [Subodha Magento2 Gulp Integration](https://github.com/subodha/magento-2-gulp) - Magento 2 Gulp integration.

</details>

## Open Source Extensions

### Development Utilities

- [Cypress Testing Suite](https://github.com/elgentos/magento2-cypress-testing-suite/) - A community-driven Cypress
  testing suite for Magento 2
- [Config ImportExport](https://github.com/semaio/Magento2-ConfigImportExport) - CLI Based Config Management.
- [Whoops Exceptions](https://github.com/yireo/Yireo_Whoops) - PHP Exceptions for Cool Kids in Magento 2.
- [Magento Cache Clean](https://github.com/mage2tv/magento-cache-clean) - A faster drop in replacement for bin/magento
  cache:clean with file watcher by Vinai Kopp](https://twitter.com/vinaikopp)
- [Developer Toolbar](https://github.com/mgtcommerce/Mgt_Developertoolbar) - Magento 2 Developer Toolbar.
- [Advanced Template Hints](https://github.com/ho-nl/magento2-Ho_Templatehints) - Magento 2 Template Hints Helper.
- [Scope Hints](https://github.com/avstudnitz/AvS_ScopeHint2) - Displays additional information in the Store Configuration
  by Andreas von Studnitz.
- [Magento 2 Configurator](https://github.com/ctidigital/magento2-configurator) - A Magento module initially created by
  CTI Digital to create and maintain database variables using files.
- [Auto Cache Flush](https://github.com/yireo/Yireo_AutoFlushCache) - Magento 2 module to automatically flush the cache.
- [Magento 2 PHPStorm File Templates](https://github.com/lfolco/phpstorm-m2-filetemplates) - PHPStorm Magento 2 File
  Templates.
- [MageVulnDB](https://github.com/gwillem/magevulndb) - Central repository for third party Magento extensions with known
  security issues.
- [Magento 2 Prometheus Exporter](https://github.com/run-as-root/magento2-prometheus-exporter) - Prometheus Exporter for
  common Magento Data.
- [graycoreio/magento2-cors](https://github.com/graycoreio/magento2-cors) - Enables configurable CORS Headers on the
  Magento GraphQL API.
- [bitExpert/phpstan-magento](https://github.com/bitExpert/phpstan-magento) - Magento specific extension for PHPStan
- [Dot Env](https://github.com/zepgram/magento-dotenv) - Magento 2 Environment Variable Component - Implementing Symfony Dotenv.
- [Rest Client](https://github.com/zepgram/module-rest) - Technical Magento 2 module providing simple development pattern, configurations and optimizations to make REST API requests toward external services based on Guzzle Client.
- [Magento 2 Model Generator / CRUD Generator](https://www.model-generator.com/) - A more up-to-date version of a Magento 2 Model & CRUD Generator by [Michiel Gerritsen](https://github.com/michielgerritsen)
- [Simon's Troubleshooting Guide](https://gist.github.com/ProcessEight/000245eac361cbcfeb9daf6de3c1c2e4) - A list with the most common errors you encounter during development.
- [Magewire PHP](https://github.com/magewirephp) - A Laravel Livewire port for building complex AJAX-based components with ease. Used by the Hyvä Checkout.
- [Yireo LokiComponents](https://github.com/yireo/Yireo_LokiComponents) - A library for building AJAX-driven form components with ease. Used by the Yireo Loki Checkout.

### Deployment

- [Deployer Magento2 Recipe](https://github.com/deployphp/deployer/blob/master/recipe/magento2.php) - Magento2
  deployment recipe for [deployer](https://deployer.org/).
- [Magento 2 Deployer Plus](https://github.com/jalogut/magento2-deployer-plus) - Tool based on deployer.org to perform
  zero downtime deployments of Magento 2 projects.
- [Github Actions for Magento2](https://github.com/extdn/github-actions-m2) - GitHub Actions for Magento 2 Extensions

### Localization

- [de_DE](https://github.com/splendidinternet/Magento2_German_LocalePack_de_DE) :de: - German Language Package.
- [de_CH](https://github.com/staempfli/magento2-language-de-ch) 🇨🇭 - Swiss Language Package.
- [fr_FR](https://github.com/Imaginaerum/magento2-language-fr-fr) :fr: - French Language Package.
- [da_DK](https://magentodanmark.dk/) 🇩🇰 - Danish Language Package.
- [es_AR](https://github.com/SemExpert/Magento2-language-es_ar) 🇦🇷 - Spanish (Argentina) Language Package.
- [es_ES](https://github.com/eusonlito/magento2-language-es_es) :es: - Spanish Language Package.
- [pt_BR](https://github.com/rafaelstz/traducao_magento2_pt_br) 🇧🇷 - Portuguese Brazil Language Package.
- [it_IT](https://github.com/mageplaza/magento-2-italian-language-pack) :it: - Italian Language.
- [nl_NL](https://github.com/magento-l10n/language-nl_NL) 🇳🇱 - Dutch Language Package.
- [pl_PL](https://github.com/SnowdogApps/magento2-pl_pl) 🇵🇱 - Polish Language Package.
- [tr_TR](https://github.com/hidonet/magento2-language-tr_tr) :tr: - Turkish Language Package.
- [ro_RO](https://github.com/EaDesgin/magento2-romanian-language-pack) 🇷🇴 - Romanian Language Package.
- [fi_FL](https://github.com/mageplaza/magento-2-finnish-language-pack) 🇫🇮 - Finnish Language Package.
- [ko_KR](https://github.com/mageplaza/magento-2-korean-language-pack) 🇰🇷 - Korean Language Package.
- [sk_SK](https://github.com/mageplaza/magento-2-slovak-language-pack) 🇸🇰 - Slovakian Language Package.
- [sl_SI](https://github.com/symfony-si/magento2-sl-si) 🇸🇮 - Slovenian Language Package.
- [en_GB](https://github.com/cubewebsites/magento2-language-en-gb) :gb: - British Language Package.
- [hr_HR](https://marketplace.magento.com/inchoo-language-hr-hr.html) :croatia: - Croatian Language Package.

### Search

- [Algolia Search Integration](https://github.com/algolia/algoliasearch-magento-2) - Algolia Search(SaaS) Integration.
- [Elastic Suite Integration](https://github.com/Smile-SA/elasticsuite/) - Elastic Suite Integration.
- [FastSimpleImport2](https://github.com/firegento/FireGento_FastSimpleImport2) - Wrapper for Magento 2 ImportExport functionality, which imports products and customers from arrays.
- [Disable Search Engine](https://github.com/zepgram/module-disable-search-engine) - Disable Elasticsearch and fulltext indexing for category search.

### CMS

- [Mageplaza Blog Extension](https://github.com/mageplaza/magento-2-blog-extension) - Simple, but well working Blog
  Extension.
- [Magento 2 Blog Extension by Magefan](https://github.com/magefan/module-blog) - Free Blog module for Magento 2 with
  unlimited blog posts and categories, SEO friendly, lazy load and AMP support.
- [Opengento GDPR](https://github.com/opengento/magento2-gdpr) - Magento 2 GDPR module is a must have extension for the
  largest e-commerce CMS used in the world. The module helps to be GDPR compliant.

### Marketing

- [MagePlaza Seo](https://github.com/mageplaza/magento-2-seo-extension) - Well documented multi purpose SEO Extension.
- [Magento 2 PDF](https://github.com/staempfli/magento2-module-pdf) - PDF Generator based
  on [wkhtmltopdf](http://wkhtmltopdf.org/).
- [Google Tag Manager](https://github.com/magepal/magento2-google-tag-manager) - Google Tag Manager (GTM) with Data
  Layer for Magento2.

### Adminhtml / Backend

- [Customer Force Login](https://github.com/bitExpert/magento2-force-login) - Forces customers to log in before
  accessing certain pages.
- [Checkout Tester](https://github.com/yireo/Yireo_CheckoutTester2) - Extension to quickly test Checkout changes.
- [Preview Checkout Success Page](https://github.com/magepal/magento2-preview-checkout-success-page) - quickly and
  easily preview and test your order confirmation page, without the need to placing a new order each time.
- [FireGento Fast Simple Import](https://github.com/firegento/FireGento_FastSimpleImport2) - Wrapper for Magento 2
  ImportExport functionality, which imports products and customers from arrays
- [Magento 2 Import Framework](https://github.com/techdivision/import) - A library supporting generic Magento 2 import
  functionality
- [Menu Editor](https://github.com/SnowdogApps/magento2-menu) - Provides powerful menu editor to replace category based
  menus in Magento 2.
- [PageNotFound](https://github.com/experius/Magento-2-Module-PageNotFound) - Saves upcoming 404 in your Database with
  the possibility to created a redirect.
- [Sentry.io](https://github.com/justbetter/magento2-sentry) - Application Monitoring and Error Tracking Software for
  Magento 2
- [Custom SMTP](https://github.com/magepal/magento2-gmail-smtp-app) - Configure Magento 2 to send all transactional
  email using Google App, Gmail, Amazon Simple Email Service (SES), Microsoft Office365 and other SMTP server.
- [Reset Customer Password](https://github.com/Vinai/module-customer-password-command) - Set a customer password with
  bin/magento by [Vinai Kopp](https://github.com/Vinai/).
- [Guest to Customer](https://github.com/magepal/magento2-guest-to-customer) - Quickly and easily convert existing guest
  checkout customers to registered customers.
- [Reset UI Bookmarks](https://github.com/magenizr/Magenizr_ResetUiBookmarks) - Reset UI Bookmarks allows admin users to
  reset their own UI bookmarks such as state of filters, column positions and applied sorting ( e.g Sales > Orders ).
- [Clean Admin Menu](https://github.com/redchamps/clean-admin-menu) - Merges 3rd party extensions to a single menu.
- [shkoliar/magento-grid-colors](https://github.com/shkoliar/magento-grid-colors) - Magento 2 Grid Colors module for
  colorizing admin grids. Supports saving of states with the help of grid's bookmarks.
  by [Dmitry Shkoliar](https://shkoliar.com/)
- [extdn/extension-dashboard-m2](https://github.com/extdn/extension-dashboard-m2) - A Magento 2 dashboard to display
  installed extensions. by [Magento Extension Developers Network](https://extdn.org/)
- [hivecommerce/magento2-content-fuzzyfyr](https://github.com/hivecommerce/magento2-content-fuzzyfyr) - The Content
  Fuzzyfyr module for Magento2 replaces real content with dummy content. This is for development purposes, e.g. save
  time to prepare test data and matching GDPR restrictions.
- [Disable Stock Reservation](https://github.com/AmpersandHQ/magento2-disable-stock-reservation) - This module disables the inventory reservation logic introduced as part of MSI in Magento 2.3.3.
- [Product Links Navigator](https://github.com/elninotech/ElNino_ProductLinksNavigator) - Enhances admin product-to-product navigation. Adds direct frontend/backend links to products in grids and modals, and "Parent Products" tab.

### Security

- [Magento Quality Patches](https://experienceleague.adobe.com/tools/commerce-quality-patches/index.html) - Every Magento / Adobe Commerce patch you need all in one place

### Payment Service Provider

- [PAYONE](https://github.com/PAYONE-GmbH/magento-2) - PAYONE Payment Integration.
- [Stripe](https://github.com/pmclain/module-stripe) - Stripe Payments for Magento 2.
- [Braintree Payments](https://marketplace.magento.com/paypal-module-braintree.html) - Official Braintree Integration
  for Magento2.

### Infrastructure

- [Fastly Extension](https://github.com/fastly/fastly-magento2) - Magento 2 fastly integration.
- [Ethan3600/magento2-CronjobManager](https://github.com/Ethan3600/magento2-CronjobManager) - Cron Job Manager for
  Magento 2.
- [Magento 2 Ngrok](https://github.com/shkoliar/magento-ngrok) - Magento 2 Ngrok Integration
- [Clean Media](https://github.com/sivaschenko/magento2-clean-media) - A Module that provides information about Media
  Files and potential removal options.
- [Interceptor Optimization](https://github.com/creatuity/magento2-interceptors) - New interceptors approach for Magento 2

---

### Proprietary Extensions

- [Commercebug Debugging Extension](http://store.pulsestorm.net/products/commerce-bug-3) - A Magento 2 Debug Extension.
- [Magicento](http://magicento.com/) - [PHPStorm](https://www.jetbrains.com/phpstorm/) Plugin to add Magento 2 related
  functionality.

---

#### Progressive Web Application

- [ScandiPWA Theme](https://github.com/scandipwa/base-theme) - Magento 2.3+ PWA theme based on React and Redux

---

## Blogs

### Personal Blogs

- [Alan Storm](http://alanstorm.com/category/magento-2/)
- [Fabian Schmengler](https://www.schmengler-se.de/)
- [Jigar Karangiya](https://jigarkarangiya.com/)

### Company Blogs

- [Atwix](https://www.atwix.com/blog/)
- [Classy Llama](https://www.classyllama.com/blog)
- [dev98](https://dev98.de/)
- [FireBear Studio](https://firebearstudio.com/blog)
- [Fooman](http://store.fooman.co.nz/blog)
- [inchoo](http://inchoo.net/category/magento-2/)
- [M.academy](https://m.academy/blog/)
- [integer_net blog](https://www.integer-net.com/blog/)
- [MageComp](https://magecomp.com/blog/category/magento-2/)
- [bitExpert AG](https://blog.bitexpert.de/blog/tags/magento)
- [OneStepCheckout](https://blog.onestepcheckout.com/)

### Other

- MageTalk: A Magento Community Podcast](http://magetalk.com/) - Community Podcast by [Kalen Jordan and [Phillip
  Jackson.

## Learning

- [M.academy](https://m.academy/) - Video lessons and courses for Magento 2 and Adobe Commerce.
- [MageTitans Italia 2016](https://www.youtube.com/playlist?list=PLwB4Uz_0hoVP3Fm_c4HfNPK5JdRD6DIDl) - MageTitans Italia 2016 conference recordings.
- [MageTitans MCR 2016](https://www.youtube.com/playlist?list=PLwB4Uz_0hoVMOnBRS49ICbNWOU5jhNNWC) - MageTitans Manchester 2016 conference recordings.
- [MageTitans USA/Texas 2016](https://www.youtube.com/playlist?list=PLwB4Uz_0hoVOLU7LPRNL4lAmJeAv7HQ-b) - MageTitans USA/Texas 2016 conference recordings.
- [Max Bucknell — Magento 2 JavaScript](https://www.youtube.com/watch?v=tHxebA-jOSo) - Max Bucknell's talk on Magento 2's JavaScript stack.
- [Max Pronko DevChannel](https://www.youtube.com/channel/UCxbWGz6h6KNQsi2ughRUV2Q) - Max Pronko's YouTube channel for Magento 2 development.
- [The Magento 2 Beginner Tutorial Class](https://www.youtube.com/playlist?list=PLtaXuX0nEZk9eL59JGE3ny-_GAU-z5X5D) - Free YouTube series for learning Magento 2.
- [Vinai Kopp Mage2Katas](https://www.youtube.com/channel/UCRFDWo7jTlrpEsJxzc7WyPw) - Vinai Kopp's Mage2Katas YouTube channel.
- [Mage2.tv](https://www.mage2.tv) - Magento 2 developer screencasts by Vinai Kopp.
- [magento-notes/magento2-exam-notes](https://github.com/magento-notes/magento2-exam-notes) - Preparation notes for the Magento 2 Certified Professional Developer exam.
- [magento-notes/magento2-cloud-developer-notes](https://github.com/magento-notes/magento2-cloud-developer-notes) - Preparation notes for the Magento 2 Certified Professional Cloud Developer exam.
- [roma-glushko/magento2-dev-plus-exam](https://github.com/roma-glushko/magento2-dev-plus-exam) - Preparation notes for the Magento 2 Certified Professional Developer Plus exam.
- [fisheye-academy/m2cpfed-training](https://github.com/fisheye-academy/m2cpfed-training) - Resources for the Magento 2 Certified Professional Front End Developer exam.
- [Yireo Training](https://www.yireo.com/training) - Magento 2 backend and frontend development courses.

---

## Platforms

- [StackExchange](http://magento.stackexchange.com/) - Q&A forum for Magento developers.

---

## Official Resources

- [Magento Official Website](https://www.magento.com) - Magento's official website.
- [Magento Developer Documentation](http://devdocs.magento.com/) - Official developer documentation.
- [Magento Forum](https://community.magento.com/) - Community forum run by Magento.
- [Magento GitHub Repository](https://github.com/magento/magento2) - Magento 2 GitHub repository.
- [Magento Developer Blog](https://community.magento.com/t5/Magento-DevBlog/bg-p/devblog) - Developer blog run by Magento.
- [Magento 2 data migration tool](https://github.com/magento/data-migration-tool) - Official Magento 1 → Magento 2 migration tool.
- [Magento Coding Standards](https://github.com/magento/magento-coding-standard) - Official Magento 2 advanced ruleset for PHP_CodeSniffer.
- [Magento 2 Architecture](https://github.com/magento/architecture) - Architectural discussions about Magento 2.

- Magento Masters 2017
    - [Peter Jaap Blaakmeer](https://twitter.com/PeterJaap) - CTO at [elgentos](https://www.elgentos.nl/)
    - Carmen Bremen - Freelancer at [neoshops](http://neoshops.de/)
    - Tony Brown - Technical Director at [space48](http://www.space48.com/)
    - Hirokazu Nishi
    - Brent Peterson
    - Sonja Riesterer
    - Kristof Ringleff
    - Alessandro Ronchi
    - Matthias Zeis
    - Kuba Zwolinski
    - Gabriel Guarino
    - Phillip Jackson
    - Sander Mangel
    - Raphael Petrini
    - Fabian Schmengler
    - Marius Strajeru
    - Anna Völkl
    - Ivan Chepurnyi
    - Vinai Kopp
    - Jisse Reitsma

---

## List of trustworthy Extension Developers

- [Aheadworks](https://www.aheadworks.com/)
- [Altima](https://shop.altima.net.au/)
- [Blue Jalappeno](http://bluejalappeno.com/)
- [CustomGento](https://www.customgento.com/extensions/)
- [Dotmailer](https://www.dotmailer.com/)
- [Integer-net](https://www.integer-net.com/solr-magento/)
- [Genmato](https://genmato.com/)
- [Fooman](http://store.fooman.co.nz/)
- [Ebizmarts](https://ebizmarts.com/)
- [Magemail](https://magemail.co/)
- [MagePal](https://packagist.org/packages/magepal/)
- [Modulwerft](https://www.modulwerft.com/)
- [Paradox Labs](https://www.paradoxlabs.com/)
- [The Extension Lab](https://github.com/theextensionlab/)
- [Sweet Tooth](https://www.sweettoothrewards.com/)
- [Rocket Web](http://rocketweb.com/)
- [ProxiBlue](https://www.proxiblue.com.au/)
- [Unirgy](http://www.unirgy.com/)
- [WebShopApps](http://webshopapps.com/eu/)
- [Yireo](https://www.yireo.com/)
- [FireBear Studio](https://firebearstudio.com/)

> **Magento Extension Developers Network (ExtDN)**
> The Magento Extension Developers Network (ExtDN) is a vetted network of extension developers whose core business is to
> develop and sell quality Magento extensions. I founded ExtDN to bring accountability and trust to the Magento extension
> market. ExtDN members agree to hold themselves accountable to high standards of coding, copyright and business conduct.

Explanation
by [Fooman](http://store.fooman.co.nz/blog/how-to-find-trustworthy-information-about-magento-extensions.html)

---

## Other Magento 2 related Awesome Lists

- [Mageres](https://github.com/aleron75/mageres) - Alessandro Ronchi's list of resources for Magento 1 and Magento 2.
- [Awesome PHP](https://github.com/ziadoz/awesome-php) - A curated list of awesome PHP resources.
- [Awesome Magento](https://github.com/sunel/awesome-magento) - An awesome Magento list with mixed M1 and M2 content by sunel.

---

## License

[![CC0](http://mirrors.creativecommons.org/presskit/buttons/88x31/svg/cc-zero.svg)](https://creativecommons.org/publicdomain/zero/1.0/)

To the extent possible under law, David Lambauer has waived all copyright and related or neighboring rights to this
work.

---

Thanks [Anna Völkl](https://github.com/avoelkl) & [Sander Mangel](https://github.com/sandermangel) for collecting all
the language packs!

---

Thanks [MageTitans](http://www.magetitans.co.uk/) for sharing the Talks on YouTube.
