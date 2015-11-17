<?php

namespace Plugins;

Class CsvStore extends Store
{
    // datastore section of ini file
    const VAR_FILENAME = 'filename';

    const MODE_READ     = 'r';
    const MODE_WRITE    = 'a';

    // database description
    const FLD_UPDATE    = 'update_time';
    const FLD_METRIC    = 'metric';
    const FLD_VALUE     = 'value';

	public $filename;
	public $handle;

	public function __construct($params)
	{
        if (empty($params[self::VAR_FILENAME]))
            $this->error = __METHOD__.': '.self::VAR_FILENAME.' parameter not set';
        else
    		$this->filename = $params[self::VAR_FILENAME];
	}

	public function create()
	{
		if (file_exists($this->filename))
		{
			$this->error = __METHOD__.": $this->filename datafile already exists";
			return false;
		}
		elseif (touch($this->filename))
			return true;
		else
        {
			$this->error = __METHOD__.": error creating $this->filename datafile";
			return false;
        }
	}

	public function open($mode=self::MODE_READ)
	{
        if (! file_exists($this->filename))
        {
            $this->error = __METHOD__.": $this->filename datafile does not exist";
            return false;
        }
        elseif (! $this->handle = fopen($this->filename, $mode))
        {
            $this->error = __METHOD__.": cannot open $this->filename datafile";
            return false;
        }

        return true;
	}

	public function close()
	{
        if (! $this->handle)
        {
            $this->error = __METHOD__.": $this->filename datafile is not open";
            return false;
        }
        elseif (! $this->handle = fclose($this->filename))
        {
            $this->error = __METHOD__.": cannot close $this->filename datafile";
            return false;
        }

        return true;
	}

	public function insertOne($time, $metric, $value)
	{
        if (empty($this->handle) and ! $this->open(self::MODE_WRITE))
            return false;

        if (fputcsv($this->handle, [$time, $metric, $value]))
            return 1;
        else
        {
            $this->error = sprintf(
                "%s: error inserting '%s' = '%s' at %s into %s datafile",
                __METHOD__,
                $metric,
                $value,
                date('Y-m-d H:i:s', $time),
                $this->filename
            );
            return false;
        }
	}

	public function insertMany($metrics=[])
	{
        if (empty($this->handle) and ! $this->open(self::MODE_WRITE))
            return false;

        $errors = [];
        if (! empty($metrics) and is_array($metrics))
        {
            foreach ((array)$metrics as $metric=>$arr)
                if (! fputcsv($this->handle, [$arr[0], $metric, $arr[1]]))
                    $errors[] = sprintf(
                        "%s: error inserting '%s' = '%s' at %s into %s datafile",
                        __METHOD__,
                        $metric,
                        $arr[1],
                        date('Y-m-d H:i:s', $arr[0]),
                        $this->filename
                    );
        }

        if ($errors)
        {
            $this->error = implode(PHP_EOL, $errors);
            return false;
        }
        else
            return true;
	}
}
