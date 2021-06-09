# AMF WordPress

Use another WordPress site as source for your media library.

## About

AMF WordPress uses the [Asset Manager Framework](https://github.com/humanmade/asset-manager-framework) (AMF) to use another WordPress site as media source for your WordPress installation.
This allows you to insert images anywhere they're used in WordPress, including the Block Editor, featured images, and the Customiser, all from the same media library.

**Note:** Currently, whatever WordPress site is set will **replace** the local media library of any site that activates this plugin.
This is a limitation of AMF that we're working on fixing.

## Installation

Install with [Composer](https://getcomposer.org):

```
composer require humanmade/amf-wordpress
```

## Settings

### URL

By default the plugin will use the current site's media library.
This should be changed at **Settings > Media** by providing the URL of the WordPress site you'd like to use as media source.
This could be an external WordPress site or another site in a multisite installation.

This URL can also be defined at the code level in the `AMF_WORDPRESS_URL` constant.
If this constant is defined then the setting on the Media settings screen will not be shown.

### Local Multisites
 By default, the Plugin will make a remote request to the provided `AMF_WORDPRESS_URL` site.
 However, if the site is actually a subsite of a multisite setup, you can instead use database queries.
To do this, define the constant `AMF_WORDPRESS_SITE_ID` and set it to the blog id of your central library.

Note that this will internally use `switch_to_blog()`, and hence will not load plugins for the central library's site (and will keep the current site's plugins and themes loaded). This may cause incorrect or inconsistent filters to be applied, so handle with care and test extensively.

 For example:

 ```
define( 'AMF_WORDPRESS_SITE_ID', 1 );
 ```

### Filters
  `'amf/local_site_id'`: can be used to augment `AMF_WORDPRESS_SITE_ID`

  For example:
  ```
   add_filter( 'amf/local_site_id' , function( $site_id ) {
       return $site_id + 1;
   }, 10, 1 );
  ```

## License

Copyright 2021 Human Made. Licensed under the GPLv3 or later.
