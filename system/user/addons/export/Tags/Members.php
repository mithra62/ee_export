<?php

namespace Mithra62\Export\Tags;

class Members extends AbstractTag
{
    // Example tag: {exp:export:members}
    public function process()
    {
        $params = $this->params();

        $params['source'] = 'members';
        $params['source:roles'] = $this->explodeParam('roles');
        $params['source:limit'] = $this->param('limit');
        $params['source:join_start'] = $this->param('join_start');
        $params['source:join_end'] = $this->param('join_end');
        $params['source:last_login_start'] = $this->param('last_login_start');
        $params['source:last_login_join_end'] = $this->param('last_login_join_end');
        $this->compile($params);
    }
}
