# Envoy REST API

This is a WordPress Plugin.
It adds the ability to create REST API routes in a controlled, contained, & repeatable way.

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
