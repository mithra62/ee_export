<?php
namespace Mithra62\Export\Post;

use Mithra62\Export\Plugins\AbstractPost;

class EeDecrypt extends AbstractPost
{
    public function process(mixed $value): mixed
    {
        return ee('Encrypt')->decrypt($value);
    }
}