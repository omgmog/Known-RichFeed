<?php

namespace IdnoPlugins\RichFeed\Pages {

    class Admin extends \Idno\Common\Page
    {
        const BATCH_SIZE = 25;

        function getContent()
        {
            $this->adminGatekeeper();

            // Handle batch continuation via GET redirect
            if ($this->getInput('action') === 'backfill') {
                $this->runBackfillBatch();
                return;
            }

            $t = \Idno\Core\Idno::site()->template();
            $t->body = $t->draw('richfeed/admin');
            $t->title = 'Rich Feed Settings';
            $t->drawPage();
        }

        function postContent()
        {
            $this->adminGatekeeper();

            if ($this->getInput('action') === 'backfill') {
                $this->runBackfillBatch();
            } else {
                $this->forward(\Idno\Core\Idno::site()->config()->getURL() . 'admin/richfeed/');
            }
        }

        private function runBackfillBatch()
        {
            $offset = (int) $this->getInput('offset', 0);
            $totalUpdated = (int) $this->getInput('total_updated', 0);

            $types = \Idno\Common\ContentType::getRegisteredClasses();
            $entities = \Idno\Common\Entity::getFromX($types, array(), array(), self::BATCH_SIZE, $offset);

            $batchCount = 0;
            $batchUpdated = 0;

            foreach ($entities as $entity) {
                $batchCount++;

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
                    $batchUpdated++;
                }
            }

            $totalUpdated += $batchUpdated;
            $nextOffset = $offset + self::BATCH_SIZE;

            // More to process — redirect to next batch
            if ($batchCount === self::BATCH_SIZE) {
                \Idno\Core\Idno::site()->session()->addMessage(
                    "Processed " . $nextOffset . " posts so far ({$totalUpdated} updated). Continuing..."
                );
                $this->forward(
                    \Idno\Core\Idno::site()->config()->getURL()
                    . 'admin/richfeed/?action=backfill'
                    . '&offset=' . $nextOffset
                    . '&total_updated=' . $totalUpdated
                );
                return;
            }

            // Done
            $totalProcessed = $offset + $batchCount;
            \Idno\Core\Idno::site()->session()->addMessage(
                "Backfill complete. Processed {$totalProcessed} posts, updated {$totalUpdated} with unfurl data."
            );
            $this->forward(\Idno\Core\Idno::site()->config()->getURL() . 'admin/richfeed/');
        }
    }
}
