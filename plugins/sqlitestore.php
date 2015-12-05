<?php

namespace Plugins;

Class SQLiteStore extends Store
{
    // datastore section of ini file
    const VAR_FILENAME = 'filename';

    // database description
    const TBL_LOG       = 'log';
    const FLD_UPDATE    = 'update_time';
    const FLD_METRIC    = 'metric';
    const FLD_VALUE     = 'value';

    public $filename;
    public $db;
    public $statement;

    public function __construct($params)
    {
        if (empty($params[self::VAR_FILENAME]))
            $this->error = __METHOD__.': '.self::VAR_FILENAME.' parameter not set';
        else
            $this->filename = realpath($params[self::VAR_FILENAME]);
    }

    public function createStatements()
    {
        $tbl_log = self::TBL_LOG;
        $fld_update = self::FLD_UPDATE;
        $fld_metric = self::FLD_METRIC;
        $fld_value = self::FLD_VALUE;

        // primary key not specified, use automatic rowid feature
        return
<<<EOsq
CREATE TABLE IF NOT EXISTS $tbl_log (
    $fld_update INTEGER NOT NULL DEFAULT CURRENT_TIMESTAMP,
    $fld_metric TEXT NOT NULL,
    $fld_value TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS log_idx ON log ($fld_update, $fld_metric);
EOsq;
    }

    public function create()
    {
        if (file_exists($this->filename))
        {
            $this->error = __METHOD__.": $this->filename datafile already exists";
            return false;
        }

        if ($this->db = sqlite_open($this->filename, 0666, $this->error))
            return sqlite_exec($this->db, $this->createStatements(), $this->error);
        else
            return false;
    }

    public function open()
    {
        if (! file_exists($this->filename))
        {
            $this->error = __METHOD__.": $this->filename datafile does not exist";
            return false;
        }

        if (! $this->db = sqlite_open($this->filename, 0666, $this->error))
        {
            $this->error = __METHOD__.": cannot open $this->filename datafile";
            return false;
        }

        return true;
    }

    public function insertMetrics($metrics=[])
    {
        if (empty($this->db) and ! $this->open())
            return false;

        $ins = [];
        if ($metrics and is_array($metrics))
        {
            foreach ((array)$metrics as $metric=>$arr)
                $ins[] = sprintf(
                    "(%u,'%s','%s')",
                    $arr[0],
                    sqlite_escape_string($metric),
                    sqlite_escape_string($arr[1])
                );
        }

        if (sqlite_exec(
            $this->db,
            sprintf(
                'insert into %s (%s, %s, %s) values %s',
                self::TBL_LOG,
                self::FLD_UPDATE,
                self::FLD_METRIC,
                self::FLD_VALUE,
                implode(',', $ins)
            ),
            $this->error
        ))
            return sqlite_changes($this->db);
        else
            return false;
    }
}
