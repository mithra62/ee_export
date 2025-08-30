<?php
namespace Mithra62\Export\Post;

use Mithra62\Export\Plugins\AbstractPost;

class UcFirst extends AbstractPost
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function process(mixed $value): mixed
    {
        return ucfirst($value);
    }
}