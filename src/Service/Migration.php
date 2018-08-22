<?php

namespace WpEcs\Service;


use WpEcs\WordpressInstance;

class Migration
{
    /**
     * The source WordPress instance
     *
     * @var WordpressInstance
     */
    protected $source;

    /**
     * The destination WordPress instance
     *
     * @var WordpressInstance
     */
    protected $dest;

    /**
     * Migration constructor.
     *
     * @param WordpressInstance $from
     * @param WordpressInstance $to
     */
    public function __construct(WordpressInstance $from, WordpressInstance $to)
    {
        $this->source = $from;
        $this->dest   = $to;
    }

    public function migrate()
    {
        // Do the migration

        // 1. Move the database
        echo "Moving database\n";
        $this->moveDatabase();
        $this->searchReplaceDb();

        // 2. Get source S3 bucket name
        // 3. Get dest S3 bucket name
        // 4. Sync S3 buckets
        // 5. Import db into dest

        echo "Done\n";

        // 6. Find & replace db
        // 7. Clean up: remove local db dump

    }

    protected function moveDatabase()
    {
        $fh = tmpfile();
        $this->source->exportDatabase($fh);
        fseek($fh, 0);
        $this->dest->importDatabase($fh);
        fclose($fh);
    }

    protected function searchReplaceDb()
    {
        // Find & replace hostname
        $sourceHostname = $this->source->env('SERVER_NAME');
        $destHostname = $this->dest->env('SERVER_NAME');
        $this->dest->execute("wp --allow-root search-replace $sourceHostname $destHostname");

        // Find & replace S3 bucket path (for media files)
        $sourceUploads = $this->source->env('S3_UPLOADS_BASE_URL');
        $destUploads = $this->dest->env('S3_UPLOADS_BASE_URL');
        $this->dest->execute("wp --allow-root search-replace $sourceUploads $destUploads");
    }
}
