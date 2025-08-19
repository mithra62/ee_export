<?php
namespace Mithra62\Export\Output;

use Mithra62\Export\Plugins\AbstractDestination;

class Download extends AbstractDestination
{
    /**
     * @var array|string[]
     */
    public array $rules = [
        'filename' => 'required',
    ];

    public function process(string $finished_export): bool|int
    {
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . $this->getOption('filename') . "\"");
        ob_clean(); flush();
        $return = false;
        if(readfile($finished_export)) {
            $return = true;
        }
        return $return;
    }
}