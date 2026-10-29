# Main Theme theme

[Bootstrap 5](https://www.drupal.org/project/bootstrap5) subtheme.

## Development.

### Home page

Edit `templates/layout/page--front.html.twig` to compose the home page in code.
Drupal automatically selects this template for its configured front page
(currently `/node`). It uses Twig Tweak, which must remain enabled.

The template reproduces the public club home page. Content and the mailing-list
form are in `templates/includes/home-content.html.twig`; the form posts to the
existing external mailing-list service. No test subscriptions are submitted.
The privacy-policy link opens the local Privacy Policy Page (node 2).
It uses the normal `templates/layout/page.html.twig` template and its content
can be edited through Drupal.
Home-page block placement and Layout Builder are not used.
Other pages use `templates/layout/page.html.twig`.

The newsletter card and back-to-top component styles are part of the global
`scss/style.scss` stylesheet. The board image and fonts are hosted locally in
`assets/home`.

Add views and blocks in the home-page template with Twig Tweak, for example:

```twig
{{ drupal_view('your_view_id', 'block_1') }}
{{ drupal_block('your_block_plugin_id', {label_display: false}) }}
```

Use the actual view machine name and display ID from Views. For custom content
blocks, use `drupal_entity('block_content', block_id)` instead of a plugin ID.
Leave access checks enabled so Drupal can enforce permissions and cache results.
Rebuild caches with `make cr` after adding or renaming templates.

### CSS compilation.

Prerequisites: install [sass](https://sass-lang.com/install).

To compile, run from subtheme directory: `sass scss/style.scss css/style.css && sass scss/ck5style.scss css/ck5style.css`

### Font Awesome

Font Awesome Free is hosted locally and loaded on every Main Theme page through
the `maintheme/fontawesome` Drupal library. Solid, regular, and brand icons are available:

```html
<i class="fa-solid fa-dice" aria-hidden="true"></i> Roll dice
<i class="fa-regular fa-calendar" aria-hidden="true"></i> Events
<i class="fa-brands fa-facebook" aria-hidden="true"></i> Facebook
```

Keep visible text or an accessible label on icon-only buttons and links.
Drupal text formats may strip icon markup; use these examples in Twig templates
or a text format configured to allow the required elements and attributes.

Run `npm ci` in this directory to install dependencies and refresh the assets.
Run `npm run assets:fontawesome` after updating the Font Awesome dependency.
Deploy `assets/fontawesome` with the theme; production does not need `node_modules`.
Rebuild Drupal caches after changing the library definition (`make cr` from the project root).
