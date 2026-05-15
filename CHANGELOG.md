# Changelog for MageForge

All notable changes to this project will be documented in this file.

---

## UNRELEASED

### Changed

* **Inspector CSS Migration**: Migrated Inspector component from Tailwind CSS to pure Vanilla CSS for universal compatibility
  - All CSS classes now use `mageforge-*` prefix for namespace isolation
  - Removed Tailwind build dependency (`tailwind/` directory deprecated)
  - No npm build step required - direct CSS editing
  - Compatible with all Magento 2 projects regardless of frontend stack
  - Inspector CSS location: `src/view/frontend/web/css/inspector.css`

## Latest Release

## [0.21.1](https://github.com/OpenForgeProject/mageforge/compare/0.21.0...0.21.1) (2026-05-15)


### Fixed

* cast render result to string in InspectorHints ([#187](https://github.com/OpenForgeProject/mageforge/issues/187)) ([c857129](https://github.com/OpenForgeProject/mageforge/commit/c8571298a7958aaa4e8d921d5a999897d1a08cb0))
* update shas and update to node24 workflows ([36bd346](https://github.com/OpenForgeProject/mageforge/commit/36bd346ad8fc7944de19505796fafc890016b0e3))

## [0.21.0](https://github.com/OpenForgeProject/mageforge/compare/0.20.0...0.21.0) (2026-05-11)


### Added

* add toolbar position configuration and implement in UI ([3f331bf](https://github.com/OpenForgeProject/mageforge/commit/3f331bf0f0cdd8e9b3fd5be6e9d41506dc281db5))
* health scoring ([#182](https://github.com/OpenForgeProject/mageforge/issues/182)) ([89bba7c](https://github.com/OpenForgeProject/mageforge/commit/89bba7c62b3755729d1ec169026c72aa18691f7b))


### Fixed

* exclude toolbar elements from tab-order & mark overflow-clipped focusables as unreachable ([1484347](https://github.com/OpenForgeProject/mageforge/commit/1484347108235591ae5e1798e232fa7af7ee6c11))


### Changed

* centralize inspector configuration constants in Inspector class ([b7c9027](https://github.com/OpenForgeProject/mageforge/commit/b7c9027c242ba23dcf2c69214ce52a41f7dbcfa3))


### Documentation

* add frontend toolbar section and image to README ([1a2fb75](https://github.com/OpenForgeProject/mageforge/commit/1a2fb756ecbd0003704e0d5a6fa62bc267624f5e))
* update toolbar image from PNG to JPEG format ([1e1bafd](https://github.com/OpenForgeProject/mageforge/commit/1e1bafd824e62ce1ac4603346d5738684997b95b))

## [0.20.0](https://github.com/OpenForgeProject/mageforge/compare/0.19.1...0.20.0) (2026-04-26)


### Added

* add additional audits for accessibility and usability checks ([2dfea0d](https://github.com/OpenForgeProject/mageforge/commit/2dfea0de47b1e9b59f92260448ff5c679bdc2577))
* add additional audits for accessibility and usability checks ([ad30fe9](https://github.com/OpenForgeProject/mageforge/commit/ad30fe9461e8f31b7258e877ae7771829f530eb3))
* add additional check for elements within the toolbar ([877edcd](https://github.com/OpenForgeProject/mageforge/commit/877edcd5269bdee8eb04429d1b493479113722b5))
* add warning styles and enhance audit checks for toolbar elements ([cfab08a](https://github.com/OpenForgeProject/mageforge/commit/cfab08ac520451ddefac021f4594f433917bdf2f))
* clarify description for unsafe target="_blank" audit ([2df5ff2](https://github.com/OpenForgeProject/mageforge/commit/2df5ff2f5ce91adbd48ed8d094da5ecfc5f8036a))
* enhance audits for duplicate IDs and interactive elements ([002d30f](https://github.com/OpenForgeProject/mageforge/commit/002d30fda814240cbb1122f2823521eb12c64607))
* exclude toolbar elements from highlight application ([bfdcc29](https://github.com/OpenForgeProject/mageforge/commit/bfdcc29a6362cb0b8a2085f66228b30aaa9d9236))
* update minimum size for small touch targets audit ([d492c1e](https://github.com/OpenForgeProject/mageforge/commit/d492c1edcb7dc99389f3fadfb59fe8aa5cc6827c))
* update touch target size description for accessibility audit ([81ae5e4](https://github.com/OpenForgeProject/mageforge/commit/81ae5e42b782c2a619503a6ee9c0e8dc598af7ea))


### Fixed

* add aria-expanded attribute for burger button accessibility ([363403b](https://github.com/OpenForgeProject/mageforge/commit/363403b0252c79db27d8d3e6367cae13cd8ecac9))
* cast show labels to integer for consistent data attribute ([37658fb](https://github.com/OpenForgeProject/mageforge/commit/37658fb5e1c3e98dd800722f2c95761da4612d42))
* disable inspector in config and update layout reference ([#175](https://github.com/OpenForgeProject/mageforge/issues/175)) ([99c71a9](https://github.com/OpenForgeProject/mageforge/commit/99c71a9d4566bf90782e7e26a72edc3ff3ab5486))
* enhance constructor PHPDoc for Inspector and ThemeSuggester classes ([ad2eb35](https://github.com/OpenForgeProject/mageforge/commit/ad2eb35a77dfef3a93689cd0b26d5ad4cfa8e201))
* ensure audits are deactivated on toolbar destruction ([b1a16fd](https://github.com/OpenForgeProject/mageforge/commit/b1a16fd2770b62e7524da5139f30ab85148e31f5))
* improve type safety and clean up PHPDoc in Inspector block ([0c63ac3](https://github.com/OpenForgeProject/mageforge/commit/0c63ac316f32e8f8b74892f8b53f85988eefe883))
* normalise opacity visibility check across all toolbar audits ([0fb1642](https://github.com/OpenForgeProject/mageforge/commit/0fb16422ef56c406c3d053a6145c2ebadda417ec))
* refine theme path matching and enhance theme suggestion logic ([3484f34](https://github.com/OpenForgeProject/mageforge/commit/3484f349bf7ab660b9f5574811d675de2ca75f3f))
* refine theme path matching and enhance theme suggestion logic ([fc1747b](https://github.com/OpenForgeProject/mageforge/commit/fc1747b90cba6a28176225aee7631a1d1744df70))
* streamline toolbar destruction process and clean up references ([65221fe](https://github.com/OpenForgeProject/mageforge/commit/65221fe830848b823b63c3d0806864e28309f2d0))
* toolbar fixes ([#177](https://github.com/OpenForgeProject/mageforge/issues/177)) ([f4382b5](https://github.com/OpenForgeProject/mageforge/commit/f4382b52420954e039820b96cc55fa5c8bbecf29))
* update aria-expanded attribute for collapsible menu groups ([96ac5e1](https://github.com/OpenForgeProject/mageforge/commit/96ac5e17591ce181484d0bbbb8f3237adb21accd))


### Styling

* adjust toolbar dimensions for better usability on small devices ([d41c6e2](https://github.com/OpenForgeProject/mageforge/commit/d41c6e2bfb8eb1659d9dd23a37e40aaf79b9f278))
* update toolbar CSS for improved visibility ([84f80de](https://github.com/OpenForgeProject/mageforge/commit/84f80de1ab7884fe057e27d7356d4e9c31b815a5))
* update toolbar for improved design ([4871193](https://github.com/OpenForgeProject/mageforge/commit/48711932074ec8867cfb63bd97addccf389b4783))

## [0.19.1](https://github.com/OpenForgeProject/mageforge/compare/0.19.0...0.19.1) (2026-04-22)


### Fixed

* remove cacheable attribute from inspector block ([#174](https://github.com/OpenForgeProject/mageforge/issues/174)) ([e12e44e](https://github.com/OpenForgeProject/mageforge/commit/e12e44ecaf5b1372705fe90ef01f476e554e1639))


### Documentation

* Fix commands.md numbering, missing sections, and flag inconsistencies ([#172](https://github.com/OpenForgeProject/mageforge/issues/172)) ([ba5498f](https://github.com/OpenForgeProject/mageforge/commit/ba5498fe8d5741d03da2fb5350003c2f5085d07f))

## [0.19.0](https://github.com/OpenForgeProject/mageforge/compare/0.18.0...0.19.0) (2026-04-13)


### Added

* defer page timing caching until load event and add global metrics ([6721e60](https://github.com/OpenForgeProject/mageforge/commit/6721e60a7ac25118154f3a79ee1704f39ee2bd51))


### Fixed

* clamp badge dragging within viewport boundaries ([03aee99](https://github.com/OpenForgeProject/mageforge/commit/03aee99cc88b178caf97573c4bdccb7e3f1cbc4b))
* ensure drag end handler is removed on draggable removal ([8957129](https://github.com/OpenForgeProject/mageforge/commit/8957129173c01ccc506507f90e7cdbab1e9f141d))


### Changed

* enhance toolbar menu display logic with CSS transitions ([e150700](https://github.com/OpenForgeProject/mageforge/commit/e1507005803446c859a9dfa21ba25f40ee95ba63))
* simplify connector management in draggable methods ([c995694](https://github.com/OpenForgeProject/mageforge/commit/c99569479244263108368d19f63a3fca155996bc))
* update audit overlay styles and improve highlight logic ([27f1588](https://github.com/OpenForgeProject/mageforge/commit/27f15881db9d30c18e5eb78ea480d86bb0bef15e))

## [0.18.0](https://github.com/OpenForgeProject/mageforge/compare/0.17.0...0.18.0) (2026-04-13)


### Added

* toolbar audit highlighting ([4fcfc7d](https://github.com/OpenForgeProject/mageforge/commit/4fcfc7d5cda0c813906dadb2060e1496e90d4504))

## [0.17.0](https://github.com/OpenForgeProject/mageforge/compare/0.16.0...0.17.0) (2026-04-12)


### Added

* add MageForge Toolbar with basic audits ([#167](https://github.com/OpenForgeProject/mageforge/issues/167)) ([2a8a8ba](https://github.com/OpenForgeProject/mageforge/commit/2a8a8ba02f53befd2b290e06f19f89480571f27e))


### Fixed

* adjust initial badge position and add scroll handler for connector ([#162](https://github.com/OpenForgeProject/mageforge/issues/162)) ([84baaf0](https://github.com/OpenForgeProject/mageforge/commit/84baaf07e328ecec143f1dbae6db5fec504375df))
* enhance role determination for input elements in accessibility analysis ([adb7996](https://github.com/OpenForgeProject/mageforge/commit/adb799637aab11b040934738f2f47326d2249c0c))
* enhance structure rendering with additional properties ([109418b](https://github.com/OpenForgeProject/mageforge/commit/109418bb1cd5060fbd56bce30717520eeffef1cd))
* handle click outside inspector overlay correctly when pinned ([c885f81](https://github.com/OpenForgeProject/mageforge/commit/c885f8133ca6c74292248b17bbe2cbf2da805d05))
* improve badge update logic to prevent flickering ([5526e15](https://github.com/OpenForgeProject/mageforge/commit/5526e1541ec4eca0a403b1095f975d509d6da866))
* improve CSS resource matching in element resource categorization ([a1dba6a](https://github.com/OpenForgeProject/mageforge/commit/a1dba6af5557831626f91f12f6833b4282e821b4))
* remove INP metric tracking from performance and vitals modules ([#165](https://github.com/OpenForgeProject/mageforge/issues/165)) ([6c78a39](https://github.com/OpenForgeProject/mageforge/commit/6c78a399f78f1b13a56db5a96098e3ff7a5a1a1c))
* remove unused badge offset in position calculation for info badge ([686387b](https://github.com/OpenForgeProject/mageforge/commit/686387b2fad00af960249b329362bdcd8b470da4))
* update cursor check for interactive elements in accessibility analysis ([c430f99](https://github.com/OpenForgeProject/mageforge/commit/c430f99d7c8b2decb4a494df7cc721ca2bfd1b21))
* update pull request header for clarity on changes ([ccd5a47](https://github.com/OpenForgeProject/mageforge/commit/ccd5a47c6c50093194cfd14ab8645c0b18415ac9))
* update pull request title and header formatting in config ([0dbab2e](https://github.com/OpenForgeProject/mageforge/commit/0dbab2ecf6b233b0534e8106d0e9a5951708b144))


### Changed

* remove feature views and new badge logic from inspector ([#164](https://github.com/OpenForgeProject/mageforge/issues/164)) ([3bf7143](https://github.com/OpenForgeProject/mageforge/commit/3bf714332cd1d85d3ca5235318a7e3adbc72f47a))
* use WeakMap for block data storage in inspector ([#166](https://github.com/OpenForgeProject/mageforge/issues/166)) ([5c383fd](https://github.com/OpenForgeProject/mageforge/commit/5c383fd7e099b8ebe684129cc5e9b07646d3b9eb))

## [0.16.0](https://github.com/OpenForgeProject/mageforge/compare/0.15.1...0.16.0) (2026-04-10)


### Added

* add CSP whitelist and update Alpine.js to version 3.15.11 with hash security ([#159](https://github.com/OpenForgeProject/mageforge/issues/159)) ([fa811bd](https://github.com/OpenForgeProject/mageforge/commit/fa811bddbf48d531b528163eb210335a3c01eeca))
* update PHP and Magento framework requirements in composer.json [#155](https://github.com/OpenForgeProject/mageforge/issues/155) ([#156](https://github.com/OpenForgeProject/mageforge/issues/156)) ([9a3f92d](https://github.com/OpenForgeProject/mageforge/commit/9a3f92dc102a9d7e2671d8b8330a276a18d8b042))


### Fixed

* add keydownHandler and impove performance observers to inspector ([#160](https://github.com/OpenForgeProject/mageforge/issues/160)) ([5639b87](https://github.com/OpenForgeProject/mageforge/commit/5639b87ecc92277ca429055f37382997fd48aefb))
* Refactor inspector.js into ES modules ([#161](https://github.com/OpenForgeProject/mageforge/issues/161)) ([f3fb74d](https://github.com/OpenForgeProject/mageforge/commit/f3fb74dc7e6090de172d0f1350bef713c1803ad5))
* updated advanced_usage.md for better understanding ([#158](https://github.com/OpenForgeProject/mageforge/issues/158)) ([956efe4](https://github.com/OpenForgeProject/mageforge/commit/956efe4053d6ac75de5cc04b89c685da46e94bf6))

## [0.15.1](https://github.com/OpenForgeProject/mageforge/compare/0.15.0...0.15.1) (2026-03-18)


### Fixed

* update phpstan to level 9 ([#152](https://github.com/OpenForgeProject/mageforge/issues/152)) ([c57f6c7](https://github.com/OpenForgeProject/mageforge/commit/c57f6c71c4c15a738b115b667db744b395553e5a))

## [0.15.0](https://github.com/OpenForgeProject/mageforge/compare/0.14.1...0.15.0) (2026-03-18)


### Added

* add wildcard theme resolution to build and clean commands ([#150](https://github.com/OpenForgeProject/mageforge/issues/150)) ([ec4316d](https://github.com/OpenForgeProject/mageforge/commit/ec4316def7318a88395e3566eb1415757990b181))

## [0.14.1](https://github.com/OpenForgeProject/mageforge/compare/0.14.0...0.14.1) (2026-03-12)


### Fixed

* ensure theme codes are indexed correctly in commands ([#149](https://github.com/OpenForgeProject/mageforge/issues/149)) ([f9175a7](https://github.com/OpenForgeProject/mageforge/commit/f9175a75e017965a0014cd71b940ce354ce64633))
* update Magento requirement to 2.4.7 with PHP 8.3 in README ([3b4cfca](https://github.com/OpenForgeProject/mageforge/commit/3b4cfca7a041f0dca8b9c3c88f2114789628471c))
* update Magento version to 2.4.7-p9 in compatibility workflow ([79eb0b2](https://github.com/OpenForgeProject/mageforge/commit/79eb0b264918d662f7e2308c022ea3f6ddd5ea94))
* update PHP version and Magento version in compatibility matrix ([8d662f6](https://github.com/OpenForgeProject/mageforge/commit/8d662f6864f9a8a7f5a78ab508dc59fe54712adb))

## [0.14.0](https://github.com/OpenForgeProject/mageforge/compare/0.13.0...0.14.0) (2026-03-06)


### Added

* replace MultiSelectPrompt with MultiSearchPrompt for theme selection ([bc89442](https://github.com/OpenForgeProject/mageforge/commit/bc89442fa6f9d77043462c02e842437211065cd7))

## [0.13.0](https://github.com/OpenForgeProject/mageforge/compare/0.12.0...0.13.0) (2026-03-03)


### Added

* add NodeSetupValidator for validating Magento default setup files ([#142](https://github.com/OpenForgeProject/mageforge/issues/142)) ([3f36d43](https://github.com/OpenForgeProject/mageforge/commit/3f36d433a04ac31be0ca84f04cd4495245ecf52e))


### Fixed

* resolve phpcs errors ([#145](https://github.com/OpenForgeProject/mageforge/issues/145)) ([cb90564](https://github.com/OpenForgeProject/mageforge/commit/cb905645175d6dd49d79b787b5545e1b6c0df422))
* run mago format ([#146](https://github.com/OpenForgeProject/mageforge/issues/146)) ([fa34cd8](https://github.com/OpenForgeProject/mageforge/commit/fa34cd8c8edc2453ec4b446b56ac16f51cd4858f))

## [0.12.0](https://github.com/OpenForgeProject/mageforge/compare/0.11.0...0.12.0) (2026-02-14)


### Added

* **actions:** replace elasticsearch with opensearch ([#137](https://github.com/OpenForgeProject/mageforge/issues/137)) ([cc8c534](https://github.com/OpenForgeProject/mageforge/commit/cc8c53428288a45b8ad645baf87623bf4a8b9a1f))


### Fixed

* correct font size and border radius in inspector CSS ([7bb3348](https://github.com/OpenForgeProject/mageforge/commit/7bb334806a134687bd06cf55e5f27682947f3c84))
* phpcs errors ([#138](https://github.com/OpenForgeProject/mageforge/issues/138)) ([625c6da](https://github.com/OpenForgeProject/mageforge/commit/625c6da89882d19605713e4c295af713700890aa))
* **phpcs:** refactor environment variable handling in commands ([#135](https://github.com/OpenForgeProject/mageforge/issues/135)) ([9c01ce5](https://github.com/OpenForgeProject/mageforge/commit/9c01ce5fe269c0b917c95b5804542c7ee7840af3))
* update font settings and sizes in inspector CSS ([986ded6](https://github.com/OpenForgeProject/mageforge/commit/986ded6aeafe628c0549dc6d72f6ae119421d9df))


### Documentation

* update README with custom theme details and add inspector section ([084b528](https://github.com/OpenForgeProject/mageforge/commit/084b52815adb78b4292d748fc100a8be3994844c))

## [0.11.0](https://github.com/OpenForgeProject/mageforge/compare/0.10.3...0.11.0) (2026-02-10)


### Added

* add cache and webvitals tabs to inspector and improve ux with dragable overlay ([#127](https://github.com/OpenForgeProject/mageforge/issues/127)) ([eea755d](https://github.com/OpenForgeProject/mageforge/commit/eea755d1b38fe9e736a49157192babc5b98fbb10))
* implement dark/light mode theme selection and admin configuration section ([#131](https://github.com/OpenForgeProject/mageforge/issues/131)) ([0a95280](https://github.com/OpenForgeProject/mageforge/commit/0a952808b6a5f7102aa5ad4b874e576d67f77bd1))


### Fixed

* remove metric icons and rename metric titles for better ux ([#129](https://github.com/OpenForgeProject/mageforge/issues/129)) ([11d3c45](https://github.com/OpenForgeProject/mageforge/commit/11d3c45991684e5936862386e5a6e731ce7962ff))

## [0.10.3](https://github.com/OpenForgeProject/mageforge/compare/0.10.2...0.10.3) (2026-02-05)


### Fixed

* update phpcs errors ([#124](https://github.com/OpenForgeProject/mageforge/issues/124)) ([a19e23d](https://github.com/OpenForgeProject/mageforge/commit/a19e23d6bfc61bb255057ed739b3904855971e2f))

## [0.10.2](https://github.com/OpenForgeProject/mageforge/compare/0.10.1...0.10.2) (2026-02-02)


### Fixed

* return theme parts as array in parseThemeName ([#122](https://github.com/OpenForgeProject/mageforge/issues/122)) ([94aef44](https://github.com/OpenForgeProject/mageforge/commit/94aef44bbc92ef6506ef6cd306ce010f1d6b6ef8))

## [0.10.1](https://github.com/OpenForgeProject/mageforge/compare/0.10.0...0.10.1) (2026-01-30)


### Fixed

* correct spacing and formatting in multiple files ([#120](https://github.com/OpenForgeProject/mageforge/issues/120)) ([3f9048a](https://github.com/OpenForgeProject/mageforge/commit/3f9048abc0b0f2785aa5be3858704037ad14ce5a))
* update phpstan level to 8 and improve commands ([#119](https://github.com/OpenForgeProject/mageforge/issues/119)) ([e24e138](https://github.com/OpenForgeProject/mageforge/commit/e24e138ae2e5fcdb0013639cdb573da782fcb3fd))

## [0.10.0](https://github.com/OpenForgeProject/mageforge/compare/0.9.0...0.10.0) (2026-01-30)


### Added

* update phpstan level and add type hints ([#116](https://github.com/OpenForgeProject/mageforge/issues/116)) ([0a4a5fa](https://github.com/OpenForgeProject/mageforge/commit/0a4a5fa45ca77396a87569eca3491b569e3d3539))


### Fixed

* update phpstan level to 7 and improve error handling ([#118](https://github.com/OpenForgeProject/mageforge/issues/118)) ([7c84ae7](https://github.com/OpenForgeProject/mageforge/commit/7c84ae75580037b6764c4e570cdca06fc5a3a970))

## [0.9.0](https://github.com/OpenForgeProject/mageforge/compare/0.8.1...0.9.0) (2026-01-30)


### Added

* Node.js/Grunt setup detection for improved build process ([#114](https://github.com/OpenForgeProject/mageforge/issues/114)) ([5330e17](https://github.com/OpenForgeProject/mageforge/commit/5330e1702acc470276bd8c6ea508b0c35ac18a2f))


### Fixed

* phpstan level 5 errors [#84](https://github.com/OpenForgeProject/mageforge/issues/84) ([#100](https://github.com/OpenForgeProject/mageforge/issues/100)) ([154d15e](https://github.com/OpenForgeProject/mageforge/commit/154d15eba0db013b99cb4141dc0b2cd059147fb0))

## [0.8.1](https://github.com/OpenForgeProject/mageforge/compare/0.8.0...0.8.1) (2026-01-27)


### Fixed

* improve node module installation fallback logic ([#107](https://github.com/OpenForgeProject/mageforge/issues/107)) ([c400732](https://github.com/OpenForgeProject/mageforge/commit/c400732d5dfec183adc7fde9e42ad6b99f573f0e))

## [0.8.0](https://github.com/OpenForgeProject/mageforge/compare/0.7.0...0.8.0) (2026-01-23)


### Added

* add functional-tests badge to readme.md ([#95](https://github.com/OpenForgeProject/mageforge/issues/95)) ([7108ef0](https://github.com/OpenForgeProject/mageforge/commit/7108ef0d4408b82f3010acaa00cf6f257880809d))
* add npm sync validation to NodePackageManager and theme builders ([#93](https://github.com/OpenForgeProject/mageforge/issues/93)) ([5fcbdaf](https://github.com/OpenForgeProject/mageforge/commit/5fcbdaf7ceb19136f4b86fbffd33b44cee1469d6))
* add phpstan & phpcs ([#96](https://github.com/OpenForgeProject/mageforge/issues/96)) ([06bcfdc](https://github.com/OpenForgeProject/mageforge/commit/06bcfdc82b1a8e0a58ad8712f7a120dc215e850f))
* add pinning functionality for inspector badge ([#104](https://github.com/OpenForgeProject/mageforge/issues/104)) ([69f7328](https://github.com/OpenForgeProject/mageforge/commit/69f73287754b572ea27349fcbb248f351dd7bc0d))
* enhance inspector with JSON metadata and comment parsing ([#105](https://github.com/OpenForgeProject/mageforge/issues/105)) ([a2f9ebf](https://github.com/OpenForgeProject/mageforge/commit/a2f9ebf4a188e01576d980866c92b7d9e7bf55f1))
* separate functional tests from compatibility tests ([effac26](https://github.com/OpenForgeProject/mageforge/commit/effac2637837efa4814b93bc1b09eb8cef306544))
* update feature request link to direct to new issue template ([7e0b57e](https://github.com/OpenForgeProject/mageforge/commit/7e0b57eb33f927ba307b008af504de42c4a2a5af))


### Fixed

* correct head-branch regex and add new changed-files sections ([53777ea](https://github.com/OpenForgeProject/mageforge/commit/53777eac19920e4de175b2a52704ff8b0c9982de))
* labeler.yml to simplify Documentation labels ([2d96502](https://github.com/OpenForgeProject/mageforge/commit/2d96502e697fb6bc0c262867163d93086c75c9aa))
* labeler.yml to update label rules ([79c3fc0](https://github.com/OpenForgeProject/mageforge/commit/79c3fc05b8a468c76b3e69ee9b555e0460bc3928))
* remove deprecated environment retrieval method ([#98](https://github.com/OpenForgeProject/mageforge/issues/98)) ([3e11ae7](https://github.com/OpenForgeProject/mageforge/commit/3e11ae7a572dff28875167043d5f9e64e7cc67b5))
* remove unnecessary blank lines in functional tests workflow ([f1e9bb7](https://github.com/OpenForgeProject/mageforge/commit/f1e9bb7ce8ea96f20f5c23b1b4ae78138388ab53))
* update head-branch patterns and file globbing in labeler.yml ([#103](https://github.com/OpenForgeProject/mageforge/issues/103)) ([bd48b7c](https://github.com/OpenForgeProject/mageforge/commit/bd48b7ced59e405002897e595972bebf97a34bc4))
* update validateHyvaTheme to include output parameter ([#99](https://github.com/OpenForgeProject/mageforge/issues/99)) ([9b53f8d](https://github.com/OpenForgeProject/mageforge/commit/9b53f8d270df9c56ab13488c2d7c60b367e3fa47))
* Workflow permissions ([#101](https://github.com/OpenForgeProject/mageforge/issues/101)) ([c0c4c3d](https://github.com/OpenForgeProject/mageforge/commit/c0c4c3dbccda41befc7107b38279dbb11dc77db2))

## [0.7.0](https://github.com/OpenForgeProject/mageforge/compare/0.6.0...0.7.0) (2026-01-20)


### Added

* add context7 configuration file with URL and public key ([977bee0](https://github.com/OpenForgeProject/mageforge/commit/977bee0d2b2c4301bd2764b07ef82eefb92e29fb))
* add NodePackageManager service for npm dependency management ([#91](https://github.com/OpenForgeProject/mageforge/issues/91)) ([1ab623f](https://github.com/OpenForgeProject/mageforge/commit/1ab623f5d858c272b6f692acb98edd35bf15ed3d))
* implement SymlinkCleaner service and integrate into theme builders [#88](https://github.com/OpenForgeProject/mageforge/issues/88) ([#89](https://github.com/OpenForgeProject/mageforge/issues/89)) ([3f40ef6](https://github.com/OpenForgeProject/mageforge/commit/3f40ef64174686fd3c75944d00773ef83515f0f9))

## [0.6.0](https://github.com/OpenForgeProject/mageforge/compare/0.5.0...0.6.0) (2026-01-19)


### Added

* dev Inspector Overlay (Frontend) ([#85](https://github.com/OpenForgeProject/mageforge/issues/85)) ([806d04a](https://github.com/OpenForgeProject/mageforge/commit/806d04a252eb70f6f76131a672296d62a7c5372b))

## [0.5.0](https://github.com/OpenForgeProject/mageforge/compare/0.4.0...0.5.0) (2026-01-17)


### ⚠ BREAKING CHANGES

* create theme:clean command for cleaning theme static files and cache, remove old mageforge:static:clean command ([#80](https://github.com/OpenForgeProject/mageforge/issues/80))

### Added

* implement StaticContentCleaner and update theme build commands ([#83](https://github.com/OpenForgeProject/mageforge/issues/83)) ([80a6abf](https://github.com/OpenForgeProject/mageforge/commit/80a6abf8b63dfdf25233f884d3fc3c6a2648b05c))


### Fixed

* create theme:clean command for cleaning theme static files and cache, remove old mageforge:static:clean command ([#80](https://github.com/OpenForgeProject/mageforge/issues/80)) ([ffd5ec8](https://github.com/OpenForgeProject/mageforge/commit/ffd5ec89e87eb8aa3a1f19eb957bd3f95a5c50b1))
* update command aliases for consistency and clarity ([#82](https://github.com/OpenForgeProject/mageforge/issues/82)) ([34640fa](https://github.com/OpenForgeProject/mageforge/commit/34640fa88f0b622780b7257f7a74a2b469453391))

## [0.4.0](https://github.com/OpenForgeProject/mageforge/compare/0.3.1...0.4.0) (2026-01-17)


### Added

* add theme suggestion service and integrate with commands [#75](https://github.com/OpenForgeProject/mageforge/issues/75) ([#76](https://github.com/OpenForgeProject/mageforge/issues/76)) ([1347782](https://github.com/OpenForgeProject/mageforge/commit/13477823e82b81bda5412a7f8d4cbd747254d6c5))
* implement Release Please workflow and update configuration ([d814853](https://github.com/OpenForgeProject/mageforge/commit/d814853c220dd098ad11340ea179ad66f9fb01f7))


### Fixed

* adjust command argument order and clean up whitespace ([9c4fb73](https://github.com/OpenForgeProject/mageforge/commit/9c4fb7356d23c38096e798d797fdfcb4ec455ff5))
* enhance interactive mode for compatibility checks and prompts ([#79](https://github.com/OpenForgeProject/mageforge/issues/79)) ([428a133](https://github.com/OpenForgeProject/mageforge/commit/428a1338479a2ad1642ea7a353d0c73e37ef155b))
* improve theme selection and validation in TokensCommand ([#77](https://github.com/OpenForgeProject/mageforge/issues/77)) ([9167e95](https://github.com/OpenForgeProject/mageforge/commit/9167e956ddd1faebc03f39a26cf9a8434ecbe99a))


### Documentation

* update community support links to GitHub Discussions ([c67380e](https://github.com/OpenForgeProject/mageforge/commit/c67380ea8366a364ab5161b7e01c1a66872d3e24))
* update dependencies and naming conventions in Copilot instructions ([cf98266](https://github.com/OpenForgeProject/mageforge/commit/cf98266c331d41516c96eddaa32983038adc8ee4))
* update README for command list and support section ([f4fb886](https://github.com/OpenForgeProject/mageforge/commit/f4fb886ca698a8f6d4ed3800bb60937f0f88331f))

### [0.3.1] - 2026-01-12

#### Fixed

- fix: add missing static property `$cachedEnv` in CleanCommand to resolve undeclared property error

### [0.3.0] - 2026-01-12

#### Added

- feat: add verbose output support for watch task with `-v` flag
  - Shows informative messages during watch mode based on verbosity level
  - Captures and reports exit codes from npm/grunt watch commands
  - Displays clear error messages when watch mode exits with errors
  - Provides hint to use `-v` flag for verbose output in non-verbose mode
- feat: add `mageforge:theme:tokens` command to generate Hyvä design tokens from design.tokens.json or hyva.config.json
- feat: add `mageforge:hyva:compatibility:check` command to add a Hyvä compatibility checker
  - Scans Magento modules for Hyvä theme compatibility issues
  - Detects RequireJS, Knockout.js, jQuery, and UI Components usage
  - Interactive menu with Laravel Prompts for scan options
  - Options: `--show-all`, `--third-party-only`, `--include-vendor`, `--detailed`
  - Color-coded output (✓ Compatible, ⚠ Warnings, ✗ Incompatible)
  - Detailed file-level issues with line numbers
  - Exit code 1 for critical issues, 0 for success
  - Command aliases: `m:h:c:c`, `hyva:check`
- feat: add `mageforge:theme:clean` command for comprehensive cache and generated files cleanup
  - feat: add interactive multi-theme selection for theme:clean command using Laravel Prompts
  - feat: add `--all` option to clean all themes at once
  - feat: add `--dry-run` option to preview what would be cleaned without deleting
  - feat: add command alias `frontend:clean` for quick access
  - feat: add CI/CD tests for theme:clean command in compatibility workflow

#### Fixed

- fix: remove duplicate `--verbose` option from WatchCommand that conflicted with Symfony Console's built-in verbose option

#### Changed

- refactor: improve build commands to show full output in verbose mode
  - Remove `--quiet` flag from npm/grunt build commands when using verbose mode
  - Allow better debugging of build issues during theme compilation
- refactor: split complex executeCommand method into smaller, focused methods to reduce cyclomatic complexity
- docs: update copilot-instructions.md with CI/CD integration guidelines for new commands

### [0.2.2] - 2025-06-05

- feat: enhance theme command arguments for better clarity and compatibility

### [0.2.1] - 2025-06-04

- feat: reduce cyclomatic complexity
- fix: normalize theme name check to be case-insensitive for Hyva themes

### [0.2.0] - 2025-05-30

- docs: clean up `CHANGELOG.md`
- feat: add PHP 8.4 and Magento 2.4.8 compatibilty check with opensearch support
- feat: enhance MySQL and Search Engine checks for `mageforge:system:check` command
- removed: removed Github Action to watch for Changelog edits
- fix: fixed issue where missing node_modules were not being installed
- fix: fixed issue where watch output was not displayed correctly

### [0.1.0] - 2025-05-23

- docs: add cli-chooser image to README.md
- docs: simplify installation instructions in README.md
- feat: add comprehensive documentation for MageForge commands and custom ThemeBuilders
- feat: add Magento compatibility tests workflow (#35)
- feat: add spinner for theme building process in BuildThemeCommand
- feat: add system check commands for Node.js, MySQL, and environment status
- feat: enhance npm installation process with package-lock.json check
- feat: enhance system check command to display additional environment information
- feat: implement abstract command structure for improved command handling
- fix: improve theme builder to reduce cyclomatic complexity
- fix: restore TTY after prompting for theme selection in BuildThemeCommand and ThemeWatchCommand 🎨
- fix: update MageForge version command in compatibility test workflow
- refactor system and theme commands
- refactor: remove redundant docblocks and improve table headers in SystemCheckCommand
- refactor: simplify theme options retrieval in ThemeWatchCommand
- Update ListCommand.php
- Update custom_theme_builders.md
- Update magento-compatibility.yml
