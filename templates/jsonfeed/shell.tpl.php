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
    $json['hubs'] = array();
    $hub = array();
    $hub['type'] = 'WebSub';
    $hub['url'] = \Idno\Core\Idno::site()->config()->hub;
    array_push($json['hubs'], $hub);
}

    // In case this isn't a feed page, find any objects
if (empty($vars['items']) && !empty($vars['object'])) {
    $vars['items'] = array($vars['object']);
}

    // If we have a feed, add the items
    $json['items'] = array();
if (!empty($vars['items'])) {
    foreach($vars['items'] as $item) {
        if (!($item instanceof \Idno\Common\Entity)) {
            continue;
        }
        $title = $item->getTitle();
        if (empty($title)) {
            if ($description = $item->getShortDescription(5)) {
                $title = $description;
            } else {
                $title = 'New ' . $item->getContentTypeTitle();
            }
        }
        $feedItem = array();
        $feedItem['title'] = strip_tags($title);
        $feedItem['url'] = $item->getSyndicationURL();
        $feedItem['id'] = $item->getUUID();
        $feedItem['date_published'] = date('c', $item->created);

        $owner = $item->getOwner();
        if (!empty($owner)) {
            $feedItem['author'] = array();
            $feedItem['author']['name'] = $item->getAuthorName();
            $feedItem['author']['url'] = $item->getAuthorURL();
            $feedItem['author']['avatar'] = $owner->getIcon();
        }

        $feedItem['_meta'] = $item->getMetadataForFeed();
        $feedItem['content_html'] = $item->draw(true);

        if ($item instanceof \IdnoPlugins\Like\Like) {
            $feedItem['external_url'] = $item->getBody();
            $feedItem['url'] = $item->getUUID();
            unset($feedItem['content_text']);
        } else if ($item instanceof \IdnoPlugins\Status\Reply) {
            $feedItem['external_url'] = $item->inreplyto;
        } else if ($item instanceof \IdnoPlugins\Status\Status) {
            $feedItem['content_text'] = $feedItem['title'];
            if ($item->inreplyto) { $feedItem['external_url'] = $item->inreplyto;
            }
            unset($feedItem['content_html']);
            unset($feedItem['title']);
        } else if ($item instanceof \IdnoPlugins\Checkin\Checkin) {
            $feedItem['content_text'] = $item->getTitle();
            unset($feedItem['content_html']);
        }

        if ($attachments = $item->getAttachments()) {
            $feedItem['attachments'] = array();
            foreach($attachments as $attachment) {
                $attachmentItem = array();
                $attachmentItem['url'] = $attachment['url'];
                $attachmentItem['mime_type'] = $attachment['mime-type'];
                $attachmentItem['size_in_bytes'] = $attachment['length'];
                array_push($feedItem['attachments'], $attachmentItem);
            }
        }

        if ($tags = $item->getTags()) {
            $feedItem['tags'] = $tags;
        }

        // Rich Feed: add unfurled URL data and strip unfurled URLs from content
        $body = $item->body;
        if (!empty($body)) {
            $hiddenUnfurls = !empty($item->hidden_unfurls) ? $item->hidden_unfurls : array();
            $urls = \IdnoPlugins\RichFeed\Main::extractUrls($body);
            $unfurls = array();
            $unfurledUrls = array();
            foreach ($urls as $url) {
                if (in_array($url, $hiddenUnfurls)) {
                    continue;
                }
                $unfurlData = \IdnoPlugins\RichFeed\Main::getUnfurlData($url);
                if (!empty($unfurlData)) {
                    $unfurls[] = $unfurlData;
                    $unfurledUrls[] = $url;
                }
            }
            if (!empty($unfurls)) {
                $feedItem['_unfurls'] = $unfurls;
            }

            // Strip unfurled URLs from content_text (bare URLs on their own line)
            if (!empty($feedItem['content_text']) && !empty($unfurledUrls)) {
                $feedItem['content_text'] = preg_replace_callback(
                    '/^\s*(https?:\/\/[^\s]+)\s*$/mi',
                    function($match) use ($unfurledUrls, $hiddenUnfurls) {
                        $url = trim($match[1]);
                        if (in_array($url, $unfurledUrls)) {
                            return ''; // strip - it's being unfurled
                        }
                        return $match[0]; // keep
                    },
                    $feedItem['content_text']
                );
                $feedItem['content_text'] = trim($feedItem['content_text']);
            }

            // Convert content_text to content_html with Markdown and linkified URLs
            if (!empty($feedItem['content_text']) && empty($feedItem['content_html'])) {
                $html = MarkdownExtra::defaultTransform($feedItem['content_text']);

                // Linkify bare URLs not already inside <a> tags
                $html = preg_replace_callback(
                    '/((<a\b[^>]*>.*?<\/a>)|https?:\/\/[^\s<>"\']+)/is',
                    function($m) {
                        if (!empty($m[2])) return $m[0]; // already a link
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
        }

        array_push($json['items'], $feedItem);
    }
}

    echo json_encode($json, JSON_UNESCAPED_SLASHES);
