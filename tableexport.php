<?php

Class TableExport extends Export
{
    // output section of ini file
    const VAR_EXPORT_DIR    = 'export_dir';

	public $export_dir;

    public function __construct($params)
    {
        if (empty($params[self::VAR_EXPORT_DIR]) or ! is_dir($params[self::VAR_EXPORT_DIR]))
            $this->error = __METHOD__.': '.self::VAR_EXPORT_DIR.' parameter not set or is not a directory';
        else
            $this->export_dir = realpath($params[self::VAR_FILENAME]);
    }

    public function export($items)
    {
        foreach ($items as $block=>$bk_items)
        {
            ;
        }

        return true;
    }
}
