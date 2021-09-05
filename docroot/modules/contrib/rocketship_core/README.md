Rocketship Core
-----

This module contains patches. For these patches to apply, your project
should require [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches).
Read that project's README to set up your project to work with dependency
patching.

One bug that sometimes crops up with dependency patches, is that composer
doesn't pick them up immediately (if, say, a new release has an extra patch).
Either check the composer log or the composer.lock to make sure all patches
are applied properly, or run your update command twice.

## Default Content Default Language
If you install the site using Rocketship Installer, you will see a step
where you can select a language to be used for "default content", meaning
the various migrates that set up demo or default content. This form can also
be accessed at /admin/config/regional/rocketship-default-language.

## Rocketship Menu Parent Alias token:

* Used mostly for node path aliases
* If the node is in a menu, fetches the parent's path alias and prepends it
  to the current node's alias
* If that parent is also a node with the same token and is also in a menu,
  that means its alias already contained their own parent so you can safely
  build a nice alias structure based on the menu.
* Also includes hook_path_update to update all children's aliases if a parent
  changes theirs
* todo: trigger same logic when someone re-orders the menu links

## Paged current page token

* A new token, [current-page:paged-url], is available. It is identical to the
  normal current-page:url token but it adds the page query parameter if present.

## Rocketship class:

* contains helper functions, similar to \Drupal::

## Breakpoints:
* Contains the breakpoints used by all responsive image styles

## Search API
* Contains search api server (db) and index for all content types. Other modules
  update the index as needed when installed.

## Field storage
* Contains field storage definitions for fields used by other features. For
  example, field_header_paragraph.

## Image Styles
* Contains all the basic image styles (based on ratios). These are then used
  to create specific Responsive image styles which are linked to content types
  and view modes and the like. There should be no need to create any more basic
  image styles, if the design allows it of course. Use responsive image styles
  wherever possible!
* Also contains "Preview" image style, which only scales the width. This is
  the image style to use for Focal Point widgets.

## Translation information
* We now don't show the language selector anymore when using a multilingual
  site. Instead, we always show what language the user is creating something in
  . The functionality for that is located in this module.

## TokenReplacer migrate processor
Requires a string to be passed to it, and only supports global tokens. Will
replace any global tokens in the string with their values.

## Widgets
#### LinkTargetWidget
* Extends normal Link widget
* Exposes option to set target so the client can decide per link

## Display Field
This module contains a custom field type that is computed, so no database
schema. It always has one value. This custom field type is used to replace
Display Suite now that we're working with Layout Builder. What used to be
DSFields are now Field Formatters specific to that custom field type:

#### TimeAgoFormatter
* Outputs the time an entity was created as "X minutes/hours/etc ago"
* Updates with AJAX, has fallback normal date format.

#### ScrollToFormatter
* Takes an identifier and some text
* Outputs a link with the identifier as href (+ # of course)
* No support for hashtags on other pages at the moment

#### ConfigurableLink
* Provides configurable link which can be placed in any display mode ( currently supported entity types are: node, taxonomy_term)
* Available configuration options are: link text, link URL ( with autocomplete support), CSS class
* Token input is supported for title and URL

## Custom formatters
#### AuthorRender
* Field type: boolean
* Output the highest level parent's author when the field value evaluates to
  true.

#### BreadcrumbRender
* Field type: boolean
* Outputs the breadcrumb if the field value is true

#### ClassLinkFormatter
* Field type: link
* Adds option to add extra classes to the output

#### DownloadLinkFileFormatter
* Field type: file
* Extends GenericFileFormatter, adds extra option for developer set link text

#### HeaderTextFieldFormatter
* Field type: string & string_long
* Wraps output in selected wrapper

#### LinkVideoEmbedColorbox
* Field type: video_embed_field
* Alternative to thumbnail link which opens video in colorbox
* This one lets you select a field or fallback text, and use that to build a
  link which will open the video in a popup. Extra fallback; if javascript
  fails it's still a link to the video.

#### PositionBasedImageFormatter
* Field type: image
* Works with the value from field_image_position to add classes that let the
  themer know if the image should be aligned left or right

#### PostDateRender
* Field type: boolean
* If checked, outputs the created date of the highest level parent.
* Currently format is hardcoded, will be fixed so it's part of the formatter
  settings

#### StaticLinkFormatter
* Field type: link
* Allows developer to set text to be used as the link text instead of using
  user defined text
* Don't forget to disable asking for link text in field settings
* Useful if the link text is always the same, such as "Visit this website"

#### RelatedPaddedReferenceItemFormatter
* Field type: entity_reference
* other reference fields from the entity to determine the relationship (still
  needs extra filter to make sure those reference field reference content
  entities, not config entities)
* the conjunction within a single field is currently OR, so if an entity has
  term A and B, entities that have term A OR B will pop up. Plans for AND will
  have to wait, can't be done using EntityQuery, will require a refactor to a
  database query.
* you can select the conjunction *between* the multiple fields however. If
  you select AND, then there will have to be a match in every field before the
  entity can be used to pad the list
* naturally the entities that are manually added to the entity reference
  field this formatter is on are excluded, as is the entity itself
* you can set how much it should pad. If you set it to 5, it will add
  entities if needed to reach 5. By default it will attempt to reach the
  cardinality for the field, unless it is infinite then it won't pad at all
  unless a manual pad limit is set.
* you can select one other field to sort by and set the sort direction
* You can only select this formatter if the entity has at least one other
  reference field that can be used to create a "relationship"
* You can only select this formatter on reference fields which reference the
  same entity and bundle as the entity the field is attached to
* 'Force padding' will pad the list to reach the limit even if there aren't
  enough items with the relationship. If it only finds 2 items that meet the
  criteria, but the limit is 5, it'll grab 3 other items to reach the limit.

TLDR:
If you've got a "related products" reference field, the user can fill in one
or two products and you can set the formatter to add other related products
until the limit is reached. It'll do that using the reference fields selected
to create a relationship.

## Custom Fields

#### ImageDescriptionTitle
* Image description title field contains an image, title and text (also
  supports icon list or numbers)
* Used in paragraphs

#### LabelValueField
* Custom field with two inputs (both textfield)
* Useful when the client wants to create a list of label: values
* For example, dimensions of a package, properties of a building, etc
* Has a normal formatter defined, as well as a Table formatter
* Also contains a "promoted" value. This is used in the Formatters, to only
  show certain
  values on teasers for example. Hidden behind permissions, used in Product
  feature.
* NOTE: we can't filter on this field, as the label, which signifies what it
  is, is also defined by the user.

#### TabbedItem
* Custom field containing a title and a body

#### TitleDescriptionField
* Custom field containing title and textarea (for when no markup is allowed,
  this can be used instead of TabbedItem)

#### RocketshipDisplayField
* Custom computed field that always has a single value. Used to replace the loss
  of Display Suite Fields now that we use Layout Builder

# Sub-modules:

## Rocketship Content:
* migrates homepage, 404 and 403 page and sets it in system.site
* also disables frontpage, 404 and 403 metatag defaults so node metatags are used

## Rocketship SEO:
* Sets up SEO settings
* Based on Varbase, but with no Yoast (unstable) and small tweaks

## Rocketship Page:
* Creates a 'Page' content type
* Uses layout builder to make it as flexible as possible

## Rocketship Blocks:
A collection of blocks to be used with layout builder


# Layout Builder

For the basic page content type, Layout Builder is the way to go. And we still use basic pages
with Layout Builder to create overview/landing pages. But now we don't need an intermediate
overview Paragraph, we can simply embed the corresponding views/facets etc directly using
Layout Builder.

We've added a fair few additions to the Layout Builder experience after having ran our own
UX feedback trials.

- No sidebar. As soon as a CkEditor field pops up, it becomes unworkable. So we've made sure all
  Layout Builder forms open in a modal instead.
- Expanded previews. We've replaced the checkbox that toggled content preview with a dropdown, so
  people can choose to only view the content (true preview), only view the editing UI, or view both.
- We've created several Layouts that work perfectly with our own themes, but should also prove useful
  for anyone wanting a good jumping off point. We'll go over those shortly.
- Blocks export a UUID as well in their config, used to make the migrates work.

We've also expanded the default functionality with contrib modules.

- Section Library allows site editors to create reusable layouts and templates
- Layout Builder Restrictions by Role allows site developers to restrict what blocks, what layouts, and what blocks
  in what layouts certain roles can use.

##### Layout Builder & Translations
Rocketship adds a patch which allows the *block titles* of blocks added to a layout to be translated.
This block title is essentially an admin-only label, but there can be use-cases where you
want this title visible in the frontend. And for that, you need to be able to translate it.

The patch makes default layout translation possible, HOWEVER, for layout overrides we are currently
using this module:

- [Layout Builder Asymmetric Translation](https://www.drupal.org/project/layout_builder_at)

It allows users to create translations that have different blocks per language.

Field labels and custom (content) blocks can be translated using their corresponding
config translation without the patch or any fancy workarounds. They work much the same
way as if you weren't using Layout Builder at all.

### Layouts

All of our custom layouts support the following:

- Add extra classes to the outermost section wrapper
- Add BEM modifier
- Change padding at top of layout
- Change padding at bottom of layout
- Select background color for layout
- Select background image for layout
- Select full-width or normal width for the background

We have one, two, three, four and three-col-dynamic layouts. We also have a Carousel Layout,
where the blocks you select will be placed in a carousel.

##### One-Col

The one-col layout has option to enable sub-regions. Because field group does not play nicely
with layout builder (yet?) this was the easiest way to allow you to group fields within a single
layout.

##### Two, Three, Four Col
All multiple column layouts, including Dynamic, have extra options:

- Reverse Layout: If checked, the first column becomes the second column and vice-versa.
  On small screens (eg. phone, where you don't have multiple columns), the first column will
  always remain on top, no matter if this option is checked or not. Use case: if you always
  want an image to be on top, on a phone screen, you would always put the Image block in column 1.
  Then you can use the 'Reverse' option to make the Image show in the second column on normal screens.

- Column sizing: how big each column has to be, eg. 50/50, or 25/75, or 25/50/25, etc.

##### Carousel Layout
- Has extra options to determine how many slides to show at certain breakpoints.
- Whether to autoplay the carousel.
- Vertical alignment options: top, middle, bottom.
