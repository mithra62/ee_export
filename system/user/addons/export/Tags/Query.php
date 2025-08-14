<?php

namespace Mithra62\Export\Tags;

class Query extends AbstractTag
{
    // Example tag: {exp:export:query}
    public function process()
    {
        return "My tag";
    }
}
