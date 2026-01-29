<?php

namespace IdnoPlugins\RichFeed {

    class Main extends \Idno\Common\Plugin
    {
        function registerPages()
        {
            \Idno\Core\Idno::site()->routes()->addRoute('admin/richfeed/?', '\IdnoPlugins\RichFeed\Pages\Admin');
            \Idno\Core\Idno::site()->template()->extendTemplate('admin/menu/items', 'richfeed/admin/menu');
        }

        function registerEventHooks()
        {
            // Store unfurl data on entity when saved
            \Idno\Core\Idno::site()->events()->addListener('save', function (\Idno\Core\Event $event) {
                $object = $event->data()['object'];
                if (!($object instanceof \Idno\Common\Entity)) {
                    return;
                }

                $body = $object->body;
                if (empty($body)) {
                    return;
                }

                $urls = self::extractUrls($body);
                if (empty($urls)) {
                    $object->rich_unfurls = array();
                    return;
                }

                $hiddenUnfurls = !empty($object->hidden_unfurls) ? $object->hidden_unfurls : array();
                $unfurls = array();

                foreach ($urls as $url) {
                    if (in_array($url, $hiddenUnfurls)) {
                        continue;
                    }
                    $unfurlData = self::getUnfurlData($url, true); // fetch if missing
                    if (!empty($unfurlData)) {
                        $unfurls[] = $unfurlData;
                    }
                }

                $object->rich_unfurls = $unfurls;
            });
        }

        /**
         * Get unfurls for an entity (uses stored data or fetches on-the-fly)
         */
        static function getUnfurlsForEntity($entity)
        {
            // Use stored unfurls if available
            if (!empty($entity->rich_unfurls)) {
                return $entity->rich_unfurls;
            }

            // Otherwise fetch on-the-fly
            if (empty($entity->body)) {
                return array();
            }

            $hiddenUnfurls = !empty($entity->hidden_unfurls) ? $entity->hidden_unfurls : array();
            $urls = self::extractUrls($entity->body);
            $unfurls = array();

            foreach ($urls as $url) {
                if (in_array($url, $hiddenUnfurls)) {
                    continue;
                }
                $unfurlData = self::getUnfurlData($url, false);
                if (!empty($unfurlData)) {
                    $unfurls[] = $unfurlData;
                }
            }

            return $unfurls;
        }

        /**
         * Extract bare URLs from post body text
         */
        static function extractUrls($body)
        {
            $urls = array();
            if (preg_match_all('/(?<!=)(?<!["\'\(])((ht|f)tps?:\/\/[^\s<>"\']+)/i', $body, $matches)) {
                foreach ($matches[1] as $url) {
                    // Strip trailing punctuation
                    $url = rtrim($url, '.!?,;:)');
                    $urls[] = $url;
                }
            }
            return array_unique($urls);
        }

        /**
         * Get unfurl data for a URL, if available
         */
        static function getUnfurlData($url, $fetchIfMissing = false)
        {
            $object = \Idno\Entities\UnfurledUrl::getBySourceURL($url);

            // Try to fetch unfurl data if we don't have it
            if ($fetchIfMissing && (empty($object) || empty($object->data))) {
                if (empty($object)) {
                    $object = new \Idno\Entities\UnfurledUrl();
                }
                if ($object->unfurl($url)) {
                    $object->save();
                }
            }

            if (!empty($object) && !empty($object->data)) {
                $data = $object->data;
                $og = !empty($data['og']) ? $data['og'] : array();
                $twitter = !empty($data['twitter']) ? $data['twitter'] : array();
                $result = array(
                    'url' => $url,
                );

                // Title (og > twitter > html title)
                if (!empty($og['og:title'])) {
                    $result['title'] = $og['og:title'];
                } else if (!empty($twitter['twitter:title'])) {
                    $result['title'] = $twitter['twitter:title'];
                } else if (!empty($data['title'])) {
                    $result['title'] = $data['title'];
                }

                // Description
                if (!empty($og['og:description'])) {
                    $result['description'] = $og['og:description'];
                } else if (!empty($twitter['twitter:description'])) {
                    $result['description'] = $twitter['twitter:description'];
                } else if (!empty($data['description'])) {
                    $result['description'] = $data['description'];
                }

                // Image
                if (!empty($og['og:image'])) {
                    $result['image'] = $og['og:image'];
                } else if (!empty($twitter['twitter:image'])) {
                    $result['image'] = $twitter['twitter:image'];
                } else if (!empty($twitter['twitter:image:src'])) {
                    $result['image'] = $twitter['twitter:image:src'];
                }

                // Site name
                if (!empty($og['og:site_name'])) {
                    $result['site_name'] = $og['og:site_name'];
                }

                // Type
                if (!empty($og['og:type'])) {
                    $result['type'] = $og['og:type'];
                }

                // Video (for YouTube etc)
                if (!empty($og['og:video:url'])) {
                    $result['video_url'] = $og['og:video:url'];
                } else if (!empty($og['og:video'])) {
                    $result['video_url'] = $og['og:video'];
                }

                // Only return if we found useful metadata
                if (count($result) > 1) {
                    return $result;
                }
            }
            return null;
        }
    }

}
