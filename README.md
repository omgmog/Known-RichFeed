# Rich Feed

A plugin for [Known](https://github.com/idno/known) CMS that enriches the JSON feed with additional metadata.

## Description

Enhances the JSON feed output (`?_t=jsonfeed`) by adding unfurled URL data (OpenGraph metadata) to feed items. When a post contains a URL, the plugin extracts available metadata like title, description, image, and site name, and includes it in the feed as `_unfurls` data. It also renders Markdown content and strips bare unfurled URLs from the text to avoid duplication.

## Features

- Adds `_unfurls` array to feed items containing OpenGraph/Twitter Card metadata for linked URLs
- Extracts title, description, image, site name, type, and video URL from unfurled links
- Renders Markdown content as HTML in the feed
- Automatically linkifies bare URLs in content
- Strips unfurled URLs from content text to prevent duplication
- Respects hidden unfurls set by the UnfurlManager plugin

## Installation

1. Copy the `RichFeed` folder to your Known installation's `IdnoPlugins` directory
2. Enable the plugin from the Known admin panel under **Site Configuration > Plugins**

## Usage

Once enabled, access your JSON feed at `?_t=jsonfeed` to see the enriched output. Posts containing URLs will include `_unfurls` data with available metadata for each link.
