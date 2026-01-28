<?php

namespace IdnoPlugins\RichFeed {

    class Main extends \Idno\Common\Plugin
    {
        function registerPages()
        {
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
        static function getUnfurlData($url)
        {
            $object = \Idno\Entities\UnfurledUrl::getBySourceURL($url);
            if (!empty($object) && !empty($object->data)) {
                $data = $object->data;
                $result = array(
                    'url' => $url,
                );

                // Title
                if (!empty($data['og:title'])) {
                    $result['title'] = $data['og:title'];
                } else if (!empty($data['twitter:title'])) {
                    $result['title'] = $data['twitter:title'];
                }

                // Description
                if (!empty($data['og:description'])) {
                    $result['description'] = $data['og:description'];
                } else if (!empty($data['twitter:description'])) {
                    $result['description'] = $data['twitter:description'];
                }

                // Image
                if (!empty($data['og:image'])) {
                    $result['image'] = $data['og:image'];
                } else if (!empty($data['twitter:image'])) {
                    $result['image'] = $data['twitter:image'];
                } else if (!empty($data['twitter:image:src'])) {
                    $result['image'] = $data['twitter:image:src'];
                }

                // Site name
                if (!empty($data['og:site_name'])) {
                    $result['site_name'] = $data['og:site_name'];
                }

                // Type
                if (!empty($data['og:type'])) {
                    $result['type'] = $data['og:type'];
                }

                // Video (for YouTube etc)
                if (!empty($data['og:video:url'])) {
                    $result['video_url'] = $data['og:video:url'];
                } else if (!empty($data['og:video'])) {
                    $result['video_url'] = $data['og:video'];
                }

                return $result;
            }
            return null;
        }
    }

}
