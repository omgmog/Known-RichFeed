<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <h1>Rich Feed Settings</h1>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Backfill Unfurl Data</h3>
            </div>
            <div class="panel-body">
                <p>
                    This will process all existing posts and store unfurl data (titles, descriptions, images)
                    for any URLs found. This data is used in the JSON feed at <code>?_t=jsonfeed</code>.
                </p>
                <p>
                    <strong>Note:</strong> This may take a while if you have many posts. Unfurl data will only
                    be stored for URLs that have already been unfurled by Known.
                </p>
                <form action="<?= \Idno\Core\Idno::site()->config()->getURL() ?>admin/richfeed/" method="post">
                    <input type="hidden" name="action" value="backfill">
                    <?= \Idno\Core\Idno::site()->actions()->signForm('/admin/richfeed/') ?>
                    <button type="submit" class="btn btn-primary">
                        Backfill All Posts
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
