# Envoy REST API

This is a WordPress Plugin.
It adds the ability to create REST API routes in a controlled, contained, & repeatable way.

# How do I install this plugin using composer?

Add this to your project's existing `composer.json`:
```
  "repositories": [

    ...

    {
      "type": "vcs",
      "url": "https://github.com/weareenvoy/wordpress-plugin-envoy-rest-api-email.git"
    }

    ...

  ],
```

Then from the terminal, run:

```
composer require envoy/envoy-rest-api;
```

The plugin files will be placed into your project directory at:
```
/web/app/plugins/envoy-rest-api
```

Add this to your project's `.gitignore`:

```
**/plugins/envoy-rest-api
```

## Existing API routes:

### __POST__ `/wp-json/envoy/route_emails_by_state`

> This endpoint receives a web-browser's `contact-us` form submission payload and performs the role of notifying others of the submission.\
> \
> Part of this role is related to a user-defined email routing table exists in the WordPress admin area. There are two important fields in the form submission that relate to looking-up appropriate contacts to route the submission to.\
> \
> Those fields are `category` and `state`.\
> \
> The `category` will determine if routing contacts will be read from the user-defined `claimant` or `provider` table within the WordPress admin settings.\
> Then within that resulting table, the `state` field will determine which of those contacts will receive copies of the email (aka '_email routing_').

### __POST__ `/wp-json/envoy/route_emails_by_category`

> This endpoint receives a web-browser's `contact-us` form submission payload and performs the role of notifying others of the submission.\
> \
> Part of this role is related to a user-defined email routing 'category' to 'email' settings exists in the WordPress admin plugin settings for this plugin. The 'category' field in the form submission is the important field that relates to looking-up appropriate contacts to route the submission to.
