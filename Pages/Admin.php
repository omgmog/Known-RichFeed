<?php

namespace IdnoPlugins\RichFeed\Pages {

    class Admin extends \Idno\Common\Page
    {
        function getContent()
        {
            $this->adminGatekeeper();
            $t = \Idno\Core\Idno::site()->template();
            $t->body = $t->draw('richfeed/admin');
            $t->title = 'Rich Feed Settings';
            $t->drawPage();
        }

        function postContent()
        {
            $this->adminGatekeeper();

            if ($this->getInput('action') === 'backfill') {
                $count = 0;
                $updated = 0;

                // Get all content entities
                $types = \Idno\Common\ContentType::getRegisteredClasses();
                $entities = \Idno\Common\Entity::getFromX($types, array(), array(), PHP_INT_MAX, 0);

                foreach ($entities as $entity) {
                    $count++;

                    if (empty($entity->body)) {
                        continue;
                    }

                    $urls = \IdnoPlugins\RichFeed\Main::extractUrls($entity->body);
                    if (empty($urls)) {
                        continue;
                    }

                    $hiddenUnfurls = !empty($entity->hidden_unfurls) ? $entity->hidden_unfurls : array();
                    $unfurls = array();

                    foreach ($urls as $url) {
                        if (in_array($url, $hiddenUnfurls)) {
                            continue;
                        }
                        $unfurlData = \IdnoPlugins\RichFeed\Main::getUnfurlData($url, true);
                        if (!empty($unfurlData)) {
                            $unfurls[] = $unfurlData;
                        }
                    }

                    if (!empty($unfurls)) {
                        $entity->rich_unfurls = $unfurls;
                        $entity->save();
                        $updated++;
                    }
                }

                \Idno\Core\Idno::site()->session()->addMessage("Processed {$count} posts, updated {$updated} with unfurl data.");
            }

            $this->forward(\Idno\Core\Idno::site()->config()->getURL() . 'admin/richfeed/');
        }
    }
}
