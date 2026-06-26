<?php

namespace Mithra62\Export\Output;

use Mithra62\Export\Plugins\AbstractDestination;

class Download extends AbstractDestination
{
    /**
     * @var bool
     */
    protected bool $force_exit = true;

    /**
     * @var array|string[]
     */
    public array $rules = [
        'filename' => 'required',
    ];

    /**
     * @param string $finished_export
     * @return bool|int
     */
    public function process(string $finished_export): bool|int
    {
        // Sanitize filename before embedding in the Content-Disposition header.
        // Strip double-quotes, CR, LF, and backslashes — all of which can be used
        // for header injection or break the quoted-string RFC syntax.
        $raw = $this->getOption('filename') ?: 'export';
        $filename = preg_replace('/["\r\n\\\\]/', '', $raw);
        if (trim($filename) === '') {
            $filename = 'export';
        }

        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: Binary');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        ob_clean();
        flush();
        $return = false;
        if (readfile($finished_export)) {
            $return = true;
        }

        return $return;
    }
}