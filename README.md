# AMF WP Multisite

Share your media library between blogs in a WordPress multisite setup.


## About

AMF WP Multisite uses the [Asset Manager Framework](https://github.com/humanmade/asset-manager-framework) to set a default media library in your WPMU installation. This allows you to insert images anywhere they're used in WordPress, including Gutenberg, featured images, and the Customiser all from the same media library.

All the interesting functionality is provided by [AMF](https://github.com/humanmade/asset-manager-framework), and this plugin essentially acts as a demo of how to implement the framework.

*Note:* Currently, whatever default blog is set will *replace* the local media library of any blog that activates the plugin. This is a limitation of AMF that we're working on fixing.


## Installation

Install via Composer:

```sh
composer require humanmade/amf-wpmultisite
```

Alternatively, download this plugin and [Asset Manager Framework](https://github.com/humanmade/asset-manager-framework), and activate both.


## Settings

By default the plugin will use the main or first blog in your multisite by ID (usually 1), to set the blog's library that should be used, you can select it in Settings > Media.


## License

Copyright 2020 Human Made. Licensed under the GPLv2 or later.
