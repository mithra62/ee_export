<?php
namespace Mithra62\Export\Post;

use Mithra62\Export\Plugins\AbstractPost;

class UcWords extends AbstractPost
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function process(mixed $value): mixed
    {
        return ucwords($value);
    }
}