# Portable Content Specification (Working Notes)

> **Status:** Future idea. Not part of the current Export roadmap.
>
> These are brainstorming notes for a potential open specification for
> portable content between CMS platforms.

------------------------------------------------------------------------

# Background

The immediate goal of Export is to move content from one platform to
another.

Longer term, there may be value in defining a common intermediate format
instead of writing countless platform-to-platform exporters.

Instead of:

``` text
ExpressionEngine -> WordPress
ExpressionEngine -> Craft
ExpressionEngine -> Statamic
...
```

the workflow becomes:

``` text
ExpressionEngine -> Portable Content Specification
Portable Content Specification -> WordPress
Portable Content Specification -> Craft
Portable Content Specification -> Statamic
```

Each exporter/importer only targets a single specification.

------------------------------------------------------------------------

# Existing Inspiration

The closest existing pattern is **Markdown with Front Matter**.

Typical implementations use:

-   YAML Front Matter (most common)
-   TOML Front Matter
-   JSON Front Matter

Example:

``` markdown
---
title: Your Platform Isn't Broken. It's Fighting You.
slug: your-platform-isnt-broken
url: https://example.com/article
date: 2026-05-01
author: Eric Lamb
categories:
  - Architecture
tags:
  - cms
  - modernization
---

Article body...
```

This is already understood by numerous tools and static site generators.

------------------------------------------------------------------------

# Possible Extended Format

``` markdown
---
schema: mithra62/content/v1

id: 8b3f5d6c
type: article

title: Your Platform Isn't Broken. It's Fighting You.
slug: your-platform-isnt-broken

status: published

published_at: 2026-05-01T08:00:00Z
updated_at: 2026-05-12T14:31:00Z

author:
  id: 4
  name: Eric Lamb

taxonomy:
  categories:
    - Architecture
    - Modernization
  tags:
    - legacy
    - cms
    - technical-debt

seo:
  meta_title:
  meta_description:
  canonical:

relationships:
  related:
    - rebuilding-vs-refactoring
    - hidden-cost-of-technical-debt

media:
  featured:
    url:
    alt:
    caption:

custom: {}

---

Article body...
```

------------------------------------------------------------------------

# Design Goals

-   Human-readable
-   Git-friendly
-   Versioned
-   CMS-agnostic
-   Easy to diff
-   Easy to import/export
-   LLM-friendly
-   Extensible without breaking compatibility

------------------------------------------------------------------------

# Potential Names

## Portable Content Specification (PCS)

A neutral, descriptive name.

## Open Portable Content (OPC)

A little shorter and more "standard" sounding.

## Portable Content Format (PCF)

Simple and practical.

------------------------------------------------------------------------

# Versioning

Every document begins with a schema identifier.

Example:

``` yaml
schema: mithra62/content/v1
```

Future revisions could include:

    mithra62/content/v2

Importers could determine compatibility automatically.

------------------------------------------------------------------------

# Why This Matters

Today every CMS exports in its own proprietary format.

Examples:

-   WordPress → WXR XML
-   ExpressionEngine → proprietary exports / SQL
-   Craft → Project Config + database
-   Statamic → Markdown
-   Drupal → JSON/XML

A common intermediate format removes the need for every platform to
understand every other platform.

------------------------------------------------------------------------

# Current Recommendation

**Do not build this yet.**

For Export, stick with a pragmatic Markdown + YAML Front Matter export.

It solves today's problem while leaving room to evolve into a broader
specification later.

This can become a standalone project if---and only if---it proves useful
beyond Export.

For now:

> Ship Export.
>
> Learn from real-world usage.
>
> Let the specification emerge from practical experience rather than
> trying to design the perfect format up front.
