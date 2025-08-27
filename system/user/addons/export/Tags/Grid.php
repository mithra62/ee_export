<?php

namespace Mithra62\Export\Tags;

class Grid extends AbstractTag
{
    // Example tag: {exp:export:grid}
    public function process()
    {
        $params = $this->params();
        $params['source'] = 'grid';
    }
}
