<?php

require 'vendor/autoload.php';
use Michelf\MarkdownExtra;

header('Content-type: application/json');
header("Access-Control-Allow-Origin: *");

unset($vars['body']);

$json = array();
$json['version'] = "https://jsonfeed.org/version/1";

if (empty($vars['title'])) {
    if (!empty($vars['description'])) {
        $json['title'] = implode(' ', array_slice(explode(' ', strip_tags($vars['description'])), 0, 10));
    } else {
        $json['title'] = 'Known site';
    }
} else {
    $json['title'] = $vars['description'];
}

if (empty($vars['base_url'])) {
    $json['home_page_url'] = $this->getCurrentURLWithoutVar('_t');
} else {
    $json['home_page_url'] = $this->getURLWithoutVar($vars['base_url'], '_t');
}

$json['feed_url'] = $this->getCurrentURL();

if (!empty(\Idno\Core\Idno::site()->config()->description)) {
    $json['description'] = \Idno\Core\Idno::site()->config()->getDescription();
}

if (!empty(\Idno\Core\Idno::site()->config()->hub)) {
    $json['hubs'] = array(array(
        'type' => 'WebSub',
        'url' => \Idno\Core\Idno::site()->config()->hub
    ));
}

// In case this isn't a feed page, find any objects
if (empty($vars['items']) && !empty($vars['object'])) {
    $vars['items'] = array($vars['object']);
}

// Build feed items
$json['items'] = array();
if (!empty($vars['items'])) {
    foreach ($vars['items'] as $item) {
        if (!($item instanceof \Idno\Common\Entity)) {
            continue;
        }

        $title = $item->getTitle();
        if (empty($title)) {
            $title = $item->getShortDescription(5) ?: 'New ' . $item->getContentTypeTitle();
        }

        $feedItem = array(
            'title' => strip_tags($title),
            'url' => $item->getSyndicationURL(),
            'id' => $item->getUUID(),
            'date_published' => date('c', $item->created),
            '_meta' => $item->getMetadataForFeed(),
            'content_html' => $item->draw(true)
        );

        // Author
        $owner = $item->getOwner();
        if (!empty($owner)) {
            $feedItem['author'] = array(
                'name' => $item->getAuthorName(),
                'url' => $item->getAuthorURL(),
                'avatar' => $owner->getIcon()
            );
        }

        // Content type specific handling
        if ($item instanceof \IdnoPlugins\Like\Like) {
            $feedItem['external_url'] = $item->getBody();
            $feedItem['url'] = $item->getUUID();
            unset($feedItem['content_text']);
        } else if ($item instanceof \IdnoPlugins\Status\Reply) {
            $feedItem['external_url'] = $item->inreplyto;
        } else if ($item instanceof \IdnoPlugins\Status\Status) {
            $feedItem['content_text'] = $feedItem['title'];
            if ($item->inreplyto) {
                $feedItem['external_url'] = $item->inreplyto;
            }
            unset($feedItem['content_html']);
            unset($feedItem['title']);
        } else if ($item instanceof \IdnoPlugins\Checkin\Checkin) {
            $feedItem['content_text'] = $item->getTitle();
            unset($feedItem['content_html']);
        }

        // Attachments
        if ($attachments = $item->getAttachments()) {
            $feedItem['attachments'] = array();
            foreach ($attachments as $attachment) {
                $feedItem['attachments'][] = array(
                    'url' => $attachment['url'],
                    'mime_type' => $attachment['mime-type'],
                    'size_in_bytes' => $attachment['length']
                );
            }
        }

        // Tags
        if ($tags = $item->getTags()) {
            $feedItem['tags'] = $tags;
        }

        // Rich Feed: get unfurl data (stored or fetched on-the-fly)
        $unfurls = \IdnoPlugins\RichFeed\Main::getUnfurlsForEntity($item);
        $unfurledUrls = array_map(function($u) { return $u['url']; }, $unfurls);

        if (!empty($unfurls)) {
            $feedItem['_unfurls'] = $unfurls;
        }

        // Strip unfurled URLs from content_text
        if (!empty($feedItem['content_text']) && !empty($unfurledUrls)) {
            $feedItem['content_text'] = preg_replace_callback(
                '/^\s*(https?:\/\/[^\s]+)\s*$/mi',
                function($match) use ($unfurledUrls) {
                    $url = trim($match[1]);
                    return in_array($url, $unfurledUrls) ? '' : $match[0];
                },
                $feedItem['content_text']
            );
            $feedItem['content_text'] = trim($feedItem['content_text']);
        }

        // Convert content_text to content_html with Markdown
        if (!empty($feedItem['content_text']) && empty($feedItem['content_html'])) {
            $html = MarkdownExtra::defaultTransform($feedItem['content_text']);

            // Linkify bare URLs not already inside <a> tags
            $html = preg_replace_callback(
                '/((<a\b[^>]*>.*?<\/a>)|https?:\/\/[^\s<>"\']+)/is',
                function($m) {
                    if (!empty($m[2])) return $m[0];
                    $url = $m[1];
                    $punc = '';
                    while ($url && strstr('.!?,;:(', substr($url, -1))) {
                        $punc = substr($url, -1) . $punc;
                        $url = substr($url, 0, -1);
                    }
                    return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a>' . $punc;
                },
                $html
            );

            $feedItem['content_html'] = $html;
            unset($feedItem['content_text']);
        }

        $json['items'][] = $feedItem;
    }
}

echo json_encode($json, JSON_UNESCAPED_SLASHES);
